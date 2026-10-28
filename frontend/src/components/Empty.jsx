import { AudioLines } from 'lucide-react';

export default function Empty({text}) {return <div className="empty"><AudioLines size={30}/><h3>{text || 'Belum ada data.'}</h3><p>Data terbaru akan tampil di sini.</p></div>;}
