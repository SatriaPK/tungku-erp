import PageTitle from "../../components/PageTitle";
import ProductionTools from "../../components/production/ProductionTools";

export default function TasksPage({ openAction }) {
  return (
    <>
      <PageTitle
        title="Pekerjaan Produksi"
        subtitle="Tetapkan tenggat dan kirim tautan hasil produksi."
      />
      <ProductionTools operator openAction={openAction} />
    </>
  );
}
