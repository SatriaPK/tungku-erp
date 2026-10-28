export default function Summary({label,value,detail}) {return <section className="summary"><span>{label}</span><strong>{value}</strong>{detail && <small>{detail}</small>}</section>;}
