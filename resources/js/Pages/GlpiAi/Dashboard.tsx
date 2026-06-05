import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, Cpu, FileWarning, Gauge, ListChecks } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { GlpiAiLayout } from '../../Layouts/GlpiAiLayout';
import { MetricCard } from '../../Components/GlpiAi/MetricCard';

export default function Dashboard({ metrics, dryRun, autoAssign }: { metrics: Record<string, any>; dryRun: boolean; autoAssign: boolean }) {
  const chartData = [
    { name: '24h', total: metrics.last_24h ?? 0 },
    { name: '7 dias', total: metrics.last_7d ?? 0 },
  ];
  const pending = Number(metrics.pending ?? 0);
  const failures = (metrics.recent_errors ?? []).length;

  const operationalStatusLabels: Record<string, string> = {
    running: 'Em execução',
    completed: 'Concluída',
    failed: 'Falhou',
  };

  return (
    <GlpiAiLayout title="Dashboard operacional" dryRun={dryRun} autoAssign={autoAssign}>
      <Head title="Dashboard | GLPI BOT" />

      <section className="mb-5 grid gap-4 xl:grid-cols-[1fr_1fr_1fr]">
        <div className="border border-[#214064]/20 bg-[#214064] p-5 text-white shadow-sm">
          <p className="text-[11px] font-black uppercase tracking-wide text-white/55">Estado do robô</p>
          <div className="mt-3 flex items-center justify-between gap-4">
            <div>
              <p className="text-2xl font-black">{metrics.dry_run ? 'Simulação' : 'Execução real'}</p>
              <p className="mt-1 text-sm text-white/60">{metrics.auto_assign ? 'Autoatribuição habilitada' : 'Autoatribuição inativa'}</p>
            </div>
            <Cpu size={34} className="text-blue-100" />
          </div>
        </div>

        <div className="panel p-5">
          <p className="text-xs font-black uppercase tracking-wide text-slate-500">Fila de validação</p>
          <div className="mt-3 flex items-center justify-between gap-4">
            <div>
              <p className="text-3xl font-black">{pending}</p>
              <p className="mt-1 text-sm text-slate-500">sugestões aguardando decisão humana</p>
            </div>
            <Link href="/glpi-ai/suggestions?status=pending" className="btn btn-primary">Abrir fila</Link>
          </div>
        </div>

        <div className={`border p-5 shadow-sm ${failures > 0 ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white'}`}>
          <p className="text-xs font-black uppercase tracking-wide text-slate-500">Saúde recente</p>
          <div className="mt-3 flex items-center justify-between gap-4">
            <div>
              <p className="text-3xl font-black">{failures}</p>
              <p className="mt-1 text-sm text-slate-500">falhas recentes registradas</p>
            </div>
            <AlertTriangle size={32} className={failures > 0 ? 'text-[#9f2f2f]' : 'text-[var(--glpi-accent)]'} />
          </div>
        </div>
      </section>

      <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Chamados analisados" value={metrics.total_analyzed ?? 0} icon={<ListChecks size={18} />} />
        <MetricCard label="Aprovadas" value={metrics.accepted ?? 0} icon={<CheckCircle2 size={18} />} />
        <MetricCard label="Triagem manual" value={metrics.manual_triage ?? 0} icon={<FileWarning size={18} />} />
        <MetricCard label="Confiança média" value={`${Number(metrics.average_confidence ?? 0).toFixed(1)}%`} icon={<Gauge size={18} />} />
        <MetricCard label="Rejeitadas" value={metrics.rejected ?? 0} />
        <MetricCard label="Autoatribuídas" value={metrics.auto_assigned ?? 0} />
        <MetricCard label="Últimas 24h" value={metrics.last_24h ?? 0} icon={<Clock size={18} />} />
        <MetricCard label="Últimos 7 dias" value={metrics.last_7d ?? 0} />
        <MetricCard label="Jobs pendentes" value={metrics.queue_pending_jobs ?? 0} />
        <MetricCard label="Jobs falhados" value={metrics.queue_failed_jobs ?? 0} icon={<AlertTriangle size={18} />} />
        <MetricCard label="Último chamado" value={metrics.last_analyzed_ticket?.glpi_ticket_id ? `#${metrics.last_analyzed_ticket.glpi_ticket_id}` : '-'} />
        <MetricCard label="Último erro IA" value={metrics.last_ai_error ? `#${metrics.last_ai_error.glpi_ticket_id}` : '-'} />
      </section>

      <section className="panel mt-5 p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="font-black">Qualidade das decisões</h2>
            <p className="mt-1 text-sm font-semibold text-slate-500">Sinais que mostram se a fila está saudável e se o aprendizado humano está vindo com contexto.</p>
          </div>
          <Link href="/glpi-ai/audit" className="btn btn-secondary">Abrir auditoria</Link>
        </div>
        <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
          <MetricCard label="Pendentes +1h" value={metrics.quality?.pending_over_1h ?? 0} />
          <MetricCard label="Pendentes +4h" value={metrics.quality?.pending_over_4h ?? 0} />
          <MetricCard label="IA com falha" value={metrics.quality?.ai_validation_failed ?? 0} />
          <MetricCard label="Feedback com motivo" value={metrics.quality?.feedback_with_reason ?? 0} />
          <MetricCard label="Feedback sem motivo" value={metrics.quality?.feedback_without_reason ?? 0} />
          <MetricCard label="Confiança final média" value={`${Number(metrics.quality?.average_final_confidence ?? 0).toFixed(1)}%`} />
        </div>
      </section>

      <section className="mt-5 grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
        <div className="panel p-5">
          <div className="flex items-center justify-between">
            <h2 className="font-black">Volume de análises</h2>
            <span className="text-xs font-black uppercase tracking-wide text-slate-500">operacional</span>
          </div>
          <div className="mt-4 h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chartData}>
                <CartesianGrid stroke="#e2e8f0" vertical={false} />
                <XAxis dataKey="name" />
                <YAxis allowDecimals={false} />
                <Tooltip />
                <Bar dataKey="total" fill="#214064" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="panel p-5">
          <div className="flex items-center justify-between">
            <h2 className="font-black">Ocorrências recentes</h2>
            <Link href="/glpi-ai/audit" className="link-action text-sm">Ver auditoria</Link>
          </div>
          <div className="mt-4 divide-y divide-slate-200 rounded-lg border border-slate-200">
            {(metrics.recent_errors ?? []).length === 0 ? <p className="p-4 text-sm font-semibold text-slate-500">Nenhuma falha recente.</p> : null}
            {(metrics.recent_errors ?? []).map((error: any) => (
              <div key={error.id} className="bg-red-50 p-3 text-sm">
                <p className="font-black text-red-950">Chamado #{error.glpi_ticket_id}</p>
                <p className="mt-1 text-red-900">{error.error_message}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="panel mt-5 p-5">
        <div className="flex items-center justify-between">
          <h2 className="font-black">Execuções operacionais</h2>
          <span className="text-xs font-black uppercase tracking-wide text-slate-500">scheduler e comandos</span>
        </div>
        <div className="mt-4 overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-100 text-[11px] uppercase tracking-[0.16em] text-slate-500">
              <tr>
                <th className="p-3">Comando</th>
                <th className="p-3">Status</th>
                <th className="p-3">Duração</th>
                <th className="p-3">Resumo</th>
              </tr>
            </thead>
            <tbody>
              {(metrics.last_operational_runs ?? []).map((run: any) => (
                <tr key={run.id} className="border-b border-slate-200">
                  <td className="p-3 font-black text-slate-950">{run.command}</td>
                  <td className="p-3 font-bold">{operationalStatusLabels[String(run.status ?? '').toLowerCase()] ?? run.status ?? '-'}</td>
                  <td className="p-3">{run.duration_ms ? `${(Number(run.duration_ms) / 1000).toFixed(1)}s` : '-'}</td>
                  <td className="p-3 text-slate-600">{run.error_message || run.summary || '-'}</td>
                </tr>
              ))}
              {(metrics.last_operational_runs ?? []).length === 0 ? (
                <tr>
                  <td colSpan={4} className="p-4 text-sm font-semibold text-slate-500">Nenhuma execução operacional registrada ainda.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </section>
    </GlpiAiLayout>
  );
}

