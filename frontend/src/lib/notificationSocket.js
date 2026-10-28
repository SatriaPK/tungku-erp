// A notification is a hint to refetch the authenticated inbox, never trusted data.
export function connectNotifications({ url, onChange, onStatus, Socket = WebSocket }) {
  if (!url) { onStatus('polling'); return () => {}; }
  let socket, timer, stopped = false, attempts = 0;
  function connect() {
    if (stopped) return;
    onStatus('connecting');
    try {
      const parsed = new URL(url);
      if (!['ws:', 'wss:'].includes(parsed.protocol)) throw new Error('Invalid WebSocket URL');
      socket = new Socket(parsed.href);
      socket.onopen = () => { attempts = 0; onStatus('connected'); onChange(); };
      socket.onmessage = () => onChange();
      socket.onerror = () => { onStatus('polling'); socket.close(); };
      socket.onclose = () => {
        if (stopped) return;
        onStatus('polling');
        timer = setTimeout(connect, Math.min(30000, 1000 * 2 ** Math.min(attempts++, 5)));
      };
    } catch { onStatus('polling'); }
  }
  connect();
  return () => { stopped = true; clearTimeout(timer); if (socket) { socket.onopen = socket.onmessage = socket.onerror = socket.onclose = null; socket.close(); } };
}
