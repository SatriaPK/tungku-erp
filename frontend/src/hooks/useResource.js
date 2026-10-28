import { useCallback, useEffect, useState } from "react";
import { request } from "../lib/api";

export default function useResource(path, params) {
  const [state, setState] = useState({
    loading: true,
    data: null,
    error: null,
  });
  const key = JSON.stringify(params || {});
  const [version, setVersion] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    setState({ loading: true, data: null, error: null });
    if (!path) {
      setState({ loading: false, data: null, error: null });
      return;
    }
    request(path, { params: JSON.parse(key), signal: controller.signal })
      .then((data) => {
        if (!controller.signal.aborted)
          setState({ loading: false, data, error: null });
      })
      .catch((error) => {
        if (!controller.signal.aborted)
          setState({ loading: false, data: null, error });
      });
    return () => controller.abort();
  }, [path, key, version]);
  useEffect(() => {
    if (path !== "/notifications") return;
    const refresh = () => setVersion((v) => v + 1);
    window.addEventListener("inbox-updated", refresh);
    return () => window.removeEventListener("inbox-updated", refresh);
  }, [path]);
  return { ...state, reload: useCallback(() => setVersion((v) => v + 1), []) };
}
