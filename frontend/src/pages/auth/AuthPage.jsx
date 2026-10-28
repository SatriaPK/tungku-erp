import ErrorNotice from "../../components/ErrorNotice";
import Field from "../../components/Field";
import { useEffect, useState } from "react";
import { login, register } from "../../lib/api";
import schemas from "../../lib/schemas.json";
import logo from "../../../assets/figma/auth-logo.png";
import { buildBody } from "../../lib/contract";
import navigate from "../../lib/navigation.js";

export default function AuthPage({ onLogin, mode = "login" }) {
  const registering = mode === "register";
  const [values, setValues] = useState({
    email: "",
    password: "",
    name: "",
    phone: "",
    password_confirmation: "",
    crm_consent: false,
    remember: false,
  });
  const [error, setError] = useState(null),
    [busy, setBusy] = useState(false),
    [until, setUntil] = useState(0),
    [now, setNow] = useState(Date.now());
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(id);
  }, []);
  const wait = Math.max(0, Math.ceil((until - now) / 1000));
  const fields = registering ? schemas.RegisterRequest : schemas.LoginRequest;
  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      if (registering && values.password.length < 8)
        throw new Error("Password minimal 8 karakter.");
      if (registering && values.password !== values.password_confirmation)
        throw new Error("Konfirmasi password tidak cocok.");
      const body = buildBody(fields, values);
      if (registering)
        body.phone = body.phone.replace(/[\s()+-]/g, "").replace(/^0/, "62");
      onLogin(await (registering ? register(body) : login(body)));
    } catch (error) {
      setError(error);
      if (error.retryAfter) {
        setUntil(Date.now() + error.retryAfter * 1000);
        setNow(Date.now());
      }
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="auth-layout">
      <section className="auth-form">
        <div className="auth-copy">
          <h1>{registering ? "Sign Up" : "Sign In"}</h1>
        </div>
        <form onSubmit={submit}>
          <ErrorNotice error={error} />
          {fields.map((field) => (
            <Field
              key={field.name}
              field={field}
              value={values[field.name]}
              error={error?.errors?.[field.name]}
              onChange={(value) =>
                setValues((v) => ({ ...v, [field.name]: value }))
              }
            />
          ))}
          <button className="primary" disabled={busy || wait > 0}>
            {busy
              ? "Memproses..."
              : wait > 0
                ? `Tunggu ${wait} detik`
                : registering
                  ? "Daftar sebagai Klien"
                  : "Masuk"}
          </button>
        </form>
        <p className="auth-switch">
          {registering ? "Sudah punya akun?" : "Belum punya akun?"}{" "}
          <a
            href={registering ? "/login" : "/register"}
            onClick={(e) => {
              e.preventDefault();
              navigate(registering ? "/login" : "/register");
            }}
          >
            {registering ? "Masuk" : "Daftar"}
          </a>
        </p>
      </section>
      <aside className="auth-art">
        <img src={logo} alt="Tungku Recording Studio" />
      </aside>
    </main>
  );
}
