"""Stateless catalog ML service. Laravel owns authentication and database access."""
import math
import re
from datetime import datetime, timezone
from typing import Literal

from fastapi import FastAPI, HTTPException, Request
from pydantic import BaseModel, ConfigDict, Field, model_validator
from sklearn.neighbors import NearestNeighbors
from sklearn.preprocessing import StandardScaler

STAGES = ("recording", "editing", "mixing", "mastering")
FEATURES = ["track_count", "recording_hours", *STAGES, "log_price"]
app = FastAPI(title="Tungku recommendation microservice", version="1.0.0")


class CatalogPackage(BaseModel):
    model_config = ConfigDict(extra="forbid")
    id: int = Field(gt=0)
    name: str = Field(max_length=255)
    track_count: int = Field(ge=0, le=65535)
    recording_hours: int = Field(ge=0, le=65535)
    stages: list[Literal["recording", "editing", "mixing", "mastering"]] = Field(max_length=4)
    price: int = Field(ge=0, le=2**53 - 1)


class Needs(BaseModel):
    model_config = ConfigDict(extra="forbid")
    track_count: int = Field(ge=1, le=255)
    recording_hours: int = Field(ge=0, le=255)
    stages: list[Literal["recording", "editing", "mixing", "mastering"]] = Field(min_length=1, max_length=4)
    budget: int | None = Field(default=None, ge=1, le=2147483647)

    @model_validator(mode="after")
    def valid_stages(self):
        if len(set(self.stages)) != len(self.stages):
            raise ValueError("Stages must be distinct")
        if "recording" in self.stages and self.recording_hours < 1:
            raise ValueError("Recording requires at least one hour")
        return self


class CatalogRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")
    catalog: list[CatalogPackage] = Field(max_length=1000)

    @model_validator(mode="after")
    def unique_ids(self):
        if len({p.id for p in self.catalog}) != len(self.catalog):
            raise ValueError("Duplicate package IDs")
        return self


class RecommendationRequest(CatalogRequest):
    needs: Needs


@app.middleware("http")
async def request_limits(request: Request, call_next):
    # No database, user data, uploaded model files or persistent training endpoint.
    body = await request.body()
    if len(body) > 512_000:
        from fastapi.responses import JSONResponse
        return JSONResponse({"detail": "Request too large"}, status_code=413)
    response = await call_next(request)
    response.headers["Cache-Control"] = "no-store"
    return response


def catalog_data(packages):
    return [p for p in packages if not re.search(r"\b(demo|dummy|test|testing|contoh)\b", p.name, re.I)
            and p.track_count > 0 and p.price > 0 and p.stages]


def vector(package):
    return [package.track_count, package.recording_hours,
            *(float(s in package.stages) for s in STAGES), math.log1p(package.price)]


def fit(catalog):
    scaler = StandardScaler()
    scaler.fit([vector(p) for p in catalog])
    return scaler


def metadata(catalog, total, trained):
    return {"method": "standardized_nearest_neighbors" if trained else "content_based_fallback",
            "trained_at": datetime.now(timezone.utc).isoformat() if trained else None,
            "training_samples": len(catalog) if trained else 0,
            "data_source": "database_catalog_non_demo", "quality_evaluated": False,
            "runtime": "python", "features": FEATURES,
            "excluded_active_packages": total - len(catalog)}


@app.get("/health")
def health():
    return {"status": "ok", "service": "tungku-recommendation", "runtime": "python"}


@app.post("/v1/train")
def train(payload: CatalogRequest):
    catalog = catalog_data(payload.catalog)
    if len(catalog) < 2:
        raise HTTPException(422, "Minimal dua paket katalog non-demo yang valid diperlukan.")
    fit(catalog)
    return {"meta": metadata(catalog, len(payload.catalog), True),
            "report": {"sample_count": len(catalog), "generated_rows": 0, "personal_data_fields": 0,
                       "evaluation": "No verified held-out client choices. Accuracy is not established."}}


@app.post("/v1/recommend")
def recommend(payload: RecommendationRequest):
    catalog = catalog_data(payload.catalog)
    needs = payload.needs
    candidates = [p for p in catalog if p.track_count >= needs.track_count
                  and set(needs.stages).issubset(p.stages)
                  and ("recording" not in needs.stages or p.recording_hours >= needs.recording_hours)]
    trained = len(catalog) >= 2
    scores = {}
    if trained and candidates:
        scaler = fit(catalog)
        query = [needs.track_count, needs.recording_hours,
                 *(float(s in needs.stages) for s in STAGES),
                 math.log1p(needs.budget) if needs.budget is not None else 0]
        dimensions = 7 if needs.budget is not None else 6
        samples = scaler.transform([vector(p) for p in candidates])[:, :dimensions]
        target = scaler.transform([query])[:, :dimensions]
        model = NearestNeighbors(n_neighbors=len(candidates), algorithm="brute", metric="euclidean", n_jobs=1)
        model.fit(samples)
        distances, indices = model.kneighbors(target)
        for distance, index in zip(distances[0], indices[0]):
            scores[candidates[index].id] = math.floor(100 / (1 + distance / math.sqrt(dimensions)) + 0.5)
    rows = []
    for p in candidates:
        if trained:
            score = scores[p.id]
        else:
            extra = len(p.stages) - len(needs.stages)
            hours = needs.recording_hours / max(1, p.recording_hours) if "recording" in needs.stages else 1
            score = math.floor(100 * (0.5 / (1 + extra) + 0.3 * needs.track_count / p.track_count + 0.2 * hours) + 0.5)
        rows.append({"package_id": p.id, "score": score,
                     "within_budget": needs.budget is None or p.price <= needs.budget})
    prices = {p.id: p.price for p in candidates}
    rows.sort(key=lambda r: (not r["within_budget"], -r["score"], prices[r["package_id"]], r["package_id"]))
    meta = metadata(catalog, len(payload.catalog), trained)
    meta["message"] = ("Belum ada paket yang memenuhi kebutuhan. Buat penawaran kustom." if not rows else
                       "Urutan berdasarkan anggaran dan kemiripan layanan, jumlah lagu, serta durasi. Skor bukan jaminan hasil produksi." if trained else
                       "Urutan berdasarkan anggaran dan kecocokan kebutuhan dengan katalog terbaru.")
    return {"data": rows[:3], "meta": meta}
