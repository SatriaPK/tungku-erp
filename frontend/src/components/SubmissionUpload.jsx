import { useEffect, useRef, useState } from 'react';
import axios from 'axios';

const endpoint = import.meta.env.VITE_SUBMISSION_UPLOAD_URL;
export default function SubmissionUpload({ onUploaded, onBusy, busy }) {
  const [file, setFile] = useState(null), [preview, setPreview] = useState('');
  const [progress, setProgress] = useState(null), [error, setError] = useState('');
  const controller = useRef(null);
  useEffect(() => () => controller.current?.abort(), []);
  useEffect(() => {
    if (!file) { setPreview(''); return; }
    const url = URL.createObjectURL(file); setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);
  async function upload() {
    setError(''); setProgress(0); onBusy(true);
    controller.current = new AbortController();
    try {
      if (!file || file.size === 0 || file.size > 100 * 1024 * 1024) throw new Error('Pilih file berukuran 1 byte–100 MB.');
      const body = new FormData(); body.append('file', file);
      const response = await axios.post(endpoint, body, {
        signal: controller.current.signal, timeout: 120000,
        withCredentials: import.meta.env.VITE_EXTERNAL_WITH_CREDENTIALS === 'true',
        onUploadProgress: event => setProgress(event.total ? Math.round(event.loaded / event.total * 100) : 0),
      });
      const url = response.data?.data?.url ?? response.data?.url;
      if (!url || !['https:', 'http:'].includes(new URL(url).protocol)) throw new Error('Layanan upload tidak mengembalikan URL HTTP/HTTPS yang valid.');
      onUploaded(url); setProgress(null);
    } catch (error) { setError(error.message || 'Upload gagal.'); setProgress(null); } finally { onBusy(false); }
  }
  return <section className="panel"><h3>Upload File Hasil</h3>
    {!endpoint && <p className="muted">Layanan upload belum dikonfigurasi. File bisa dipratinjau; gunakan tautan hasil untuk mengirim submission.</p>}
    <label className="field"><span>File audio / arsip (maksimal 100 MB)</span><input type="file" accept="audio/*,.zip,.flac,.wav,.mp3" disabled={busy || progress !== null} onChange={event => { setFile(event.target.files?.[0] || null); setError(''); }}/></label>
    {file && <p>{file.name} · {(file.size / 1024 / 1024).toFixed(2)} MB</p>}
    {preview && file?.type.startsWith('audio/') && <audio controls src={preview}/>}
    {error && <p role="alert">{error}</p>}
    {progress !== null && <p role="status">Mengunggah {progress}%</p>}
    <button type="button" disabled={!endpoint || !file || busy || progress !== null} onClick={upload}>Upload & Gunakan Tautan</button>
    <p className="muted">Setelah upload selesai, tekan Kirim Hasil untuk menyimpan submission. Jangan upload ulang bila pengiriman submission gagal; tautan tetap tersedia.</p>
  </section>;
}
