export default function PageTitle({ title, subtitle, children }) {
  return (
    <div className="page-title">
      <div>
        <span className="eyebrow">WORKSPACE / TUNGKU STUDIO</span>
        <h1 className="type-headline-medium">{title}</h1>
        {subtitle && <p className="type-body-small">{subtitle}</p>}
      </div>
      <div className="title-actions">{children}</div>
    </div>
  );
}
