# Tungku recommendation microservice

Python FastAPI service, independently deployable on Vercel. Laravel sends only
active catalog IDs, names, service flags, capacities and effective prices plus the
requested capacities, stages and optional budget. No users, payments, credentials
or database access are sent. This is a stateless public calculation API.

`POST /v1/recommend` fits scikit-learn StandardScaler on the current valid non-demo
catalog, then uses NearestNeighbors Euclidean distances over standardized features.
Budget priority, required stages/capacities, score rounding and top-three ordering
are preserved. With fewer than two packages the original content matching fallback
also runs in Python and is labelled `content_based_fallback`.

The model is instance-based, fitted from the supplied catalog on each request.
No disk artifact can become stale after a catalog edit or a serverless cold start.
This is not supervised prediction of client preferences. No held-out choices exist,
so accuracy, precision and recall are not claimed.

Endpoints: `GET /health`, `POST /v1/train`, `POST /v1/recommend`.
Train accepts `{catalog: [...]}`; recommend adds `needs` with `track_count`,
`recording_hours`, `stages`, and optional `budget`.

Local: `pip install -r requirements.txt`, then `uvicorn app:app --port 8010`.
Tests: `pip install httpx`, then `python -m unittest -v`.
Deploy this directory as a separate Vercel FastAPI project. Set
`RECOMMENDATION_SERVICE_URL` on Laravel to its production HTTPS URL. Laravel's
existing client recommendation endpoint and JSON shape remain unchanged.
If Python is unavailable Laravel responds 503; it never runs ML locally.
