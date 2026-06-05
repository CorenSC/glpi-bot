import { Head, useForm } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { GlpiAiLayout } from '../../../Layouts/GlpiAiLayout';

export default function ManualAnalysis({ dryRun, autoAssign }: { dryRun: boolean; autoAssign: boolean }) {
  const form = useForm({ glpi_ticket_id: '' });

  return (
    <GlpiAiLayout title="Análise manual" dryRun={dryRun} autoAssign={autoAssign}>
      <Head title="Análise manual | GLPI BOT" />

      <section className="panel max-w-3xl">
        <div className="panel-header">
          <h2 className="section-title">Executar análise por ID</h2>
          <p className="mt-1 text-sm text-[var(--glpi-muted)]">Use para testar um chamado específico antes de automatizar a rotina.</p>
        </div>

        <form className="p-4" onSubmit={(event) => { event.preventDefault(); form.post('/glpi-ai/manual-analysis'); }}>
          <label>
            <span className="eyebrow mb-2 block">ID do chamado GLPI</span>
            <input
              value={form.data.glpi_ticket_id}
              onChange={(event) => form.setData('glpi_ticket_id', event.target.value)}
              className="field text-lg font-black"
              placeholder="1923"
            />
          </label>
          {form.errors.glpi_ticket_id ? <p className="mt-2 text-sm font-semibold text-red-800">{form.errors.glpi_ticket_id}</p> : null}
          <button type="submit" disabled={form.processing} className="btn btn-primary mt-4">
            <Play size={16} />
            Analisar chamado em dry-run
          </button>
        </form>
      </section>
    </GlpiAiLayout>
  );
}
