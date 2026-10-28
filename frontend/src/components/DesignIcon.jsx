const assets = import.meta.glob("../../assets/figma/*.svg", {
  eager: true,
  query: "?url",
  import: "default",
});

export default function DesignIcon({ name, className = "" }) {
  const src = assets[`../../assets/figma/${name}.svg`];
  return src ? (
    <span className={`design-icon ${className}`} aria-hidden="true">
      <img src={src} alt="" />
    </span>
  ) : null;
}
