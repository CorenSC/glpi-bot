import { Head, Link, router } from '@inertiajs/react';
import { Activity, ArrowLeft, ArrowRight, ExternalLink, Filter, History, RotateCcw, Search, UserCheck } from 'lucide-react';
import { useState } from 'react';
import { ConfidenceBadge } from '../../../Components/GlpiAi/Badges';
import { GlpiAiLayout } from '../../../Layouts/GlpiAiLayout';

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedRuns {
  data: AuditRun[];
  current_page: number;
  from: number | null;
  last_page: number;
  links: PaginationLink[];
  to: number | null;
  total: number;
}

interface AuditRun {
  id: number;
  glpi_ticket_id: number;
  status?: string;
  recommended_action?: string;
  recommended_technician_id?: number;
  recommended_group_id?: number;
  confidence?: number;
  risk_level?: string;
  dry_run?: boolean;
  auto_assign_enabled?: boolean;
  duration_ms?: number;
  model_used?: string;
  embedding_model_used?: string;
  algorithm_version?: string;
  error_message?: string;
  created_at?: string;
  started_at?: string;
  finished_at?: string;
  final_decision?: {
    reason?: string;
    warnings?: string[];
    recommended_technician_id?: number;
    recommended_group_id?: number;
  };
  ai_decision?: {
    reason?: string;
  };
  suggestion?: {
    id?: number;
    status?: string;
    recommended_technician_name?: string;
    recommended_group_name?: string;
  };
}

interface HumanFeedback {
  id: number;
  action: string;
  previous_status?: string;
  new_status?: string;
  user_id?: number;
  assignment_suggestion_id?: number;
  selected_technician_id?: number;
  selected_group_id?: number;
  observation?: string;
  ip_address?: string;
  user_agent?: string;
  created_at?: string;
  suggestion?: {
    id?: number;
    glpi_ticket_id?: number;
    title?: string;
    status?: string;
    recommended_technician_name?: string;
    recommended_group_name?: string;
  };
}

const actionLabels: Record<string, string> = {
  assign_to_technician: 'Sugerir técnico',
  assign_to_group: 'Sugerir grupo',
  manual_triage: 'Enviar para triagem manual',
  approve: 'Aprovação da sugestão',
  reject: 'Rejeição da sugestão',
  assign_recommended_technician: 'Atribuição ao técnico recomendado',
  assign_recommended_group: 'Atribuição ao grupo recomendado',
  assign_other_technician: 'Escolha de outro técnico',
  assign_other_group: 'Escolha de outro grupo',
  send_to_manual_triage: 'Envio para triagem manual',
  mark_incorrect: 'Marcação como incorreta',
  recalculate: 'Recálculo solicitado',
  ignore: 'Sugestão ignorada',
  glpi_ticket_closed: 'Chamado finalizado no GLPI',
};

const statusLabels: Record<string, string> = {
  pending: 'Pendente',
  accepted: 'Aprovada',
  rejected: 'Rejeitada',
  auto_assigned: 'Autoatribuída',
  manual_triage: 'Triagem manual',
  failed: 'Falhou',
  ignored: 'Ignorada',
  completed: 'Concluída',
  glpi_closed: 'Finalizada no GLPI',
  running: 'Em execução',
};

function glpiTicketUrl(baseUrl: string, ticketId?: number | string) {
  if (!ticketId) return undefined;
  return `${baseUrl.replace(/\/$/, '')}/front/ticket.form.php?id=${ticketId}`;
}

function labelAction(action?: string) {
  return actionLabels[action ?? ''] ?? action ?? 'Sem decisão';
}

function labelStatus(status?: string) {
  return statusLabels[String(status ?? '').toLowerCase()] ?? status ?? '-';
}

function compactDate(value?: string) {
  if (!value) return '-';

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

function duration(ms?: number) {
  const value = Number(ms ?? 0);
  if (value >= 1000) return `${(value / 1000).toFixed(1)} s`;
  return `${value} ms`;
}

function readablePageLabel(label: string) {
  if (label.includes('Previous')) return 'Anterior';
  if (label.includes('Next')) return 'Próxima';

  return label.replace('&laquo;', '').replace('&raquo;', '').trim();
}

function statusTone(status?: string) {
  const normalized = String(status ?? '').toLowerCase();
  if (['failed', 'rejected'].includes(normalized)) return 'border-red-200 bg-red-50 text-red-900';
  if (['accepted', 'auto_assigned', 'glpi_closed', 'completed'].includes(normalized)) return 'border-emerald-200 bg-emerald-50 text-emerald-800';
  if (normalized === 'pending') return 'border-amber-200 bg-amber-50 text-amber-900';
  return 'border-slate-200 bg-slate-50 text-slate-700';
}

function riskTone(risk?: string) {
  if (risk === 'high') return 'border-red-200 bg-red-50 text-red-900';
  if (risk === 'medium') return 'border-amber-200 bg-amber-50 text-amber-900';
  return 'border-emerald-200 bg-emerald-50 text-emerald-800';
}

function PageButton({ link, fallbackLabel, icon }: { link?: PaginationLink; fallbackLabel: string; icon?: 'previous' | 'next' }) {
  const label = link ? readablePageLabel(link.label) : fallbackLabel;
  const disabled = !link?.url;
  const content = (
    <>
      {icon === 'previous' ? <ArrowLeft aria-hidden="true" size={15} /> : null}
      <span>{label}</span>
      {icon === 'next' ? <ArrowRight aria-hidden="true" size={15} /> : null}
    </>
  );
  const classes = `inline-flex min-h-10 items-center justify-center gap-1.5 border px-3 text-sm font-black transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#214064] focus-visible:ring-offset-2 ${
    link?.active
      ? 'border-[#214064] bg-[#214064] text-white'
      : disabled
        ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-300'
        : 'border-slate-200 bg-white text-slate-700 hover:border-[#214064] hover:text-[#214064]'
  }`;

  if (disabled) return <span className={classes} aria-disabled="true">{content}</span>;
  return <Link href={link.url as string} preserveScroll preserveState className={classes}>{content}</Link>;
}

function Pagination({ runs }: { runs: PaginatedRuns }) {
  if (runs.last_page <= 1) return null;

  const previous = runs.links.find((link) => link.label.includes('Previous'));
  const next = runs.links.find((link) => link.label.includes('Next'));
  const pages = runs.links.filter((link) => !link.label.includes('Previous') && !link.label.includes('Next'));

  return (
    <nav className="flex flex-col gap-3 border-t border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between" aria-label="Paginação da auditoria">
      <p className="text-sm font-semibold text-slate-600">
        Mostrando <strong className="text-slate-950">{runs.from ?? 0}</strong> a <strong className="text-slate-950">{runs.to ?? 0}</strong> de <strong className="text-slate-950">{runs.total}</strong> execuções.
      </p>
      <div className="flex flex-wrap items-center gap-1.5">
        <PageButton link={previous} fallbackLabel="Anterior" icon="previous" />
        <div className="hidden items-center gap-1 sm:flex">
          {pages.map((link) => <PageButton key={`${link.label}-${link.url ?? 'disabled'}`} link={link} fallbackLabel={readablePageLabel(link.label)} />)}
        </div>
        <span className="px-2 py-2 text-sm font-black text-slate-700 sm:hidden">Página {runs.current_page}/{runs.last_page}</span>
        <PageButton link={next} fallbackLabel="Próxima" icon="next" />
      </div>
    </nav>
  );
}

function AuditMetric({ label, value, helper }: { label: string; value: string; helper: string }) {
  return (
    <div className="panel p-4">
      <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-black tabular-nums text-slate-950">{value}</p>
      <p className="mt-1 text-xs font-semibold text-slate-500">{helper}</p>
    </div>
  );
}

function Summary({ runs, feedbacks }: { runs: PaginatedRuns; feedbacks: HumanFeedback[] }) {
  const rows = runs.data ?? [];
  const failures = rows.filter((run) => run.status === 'failed' || run.error_message).length;
  const realMode = rows.filter((run) => !run.dry_run).length;
  const average = rows.length > 0 ? rows.reduce((sum, run) => sum + Number(run.confidence ?? 0), 0) / rows.length : 0;

  return (
    <section className="grid gap-3 md:grid-cols-4" aria-label="Resumo de auditoria">
      <AuditMetric label="Execuções filtradas" value={runs.total.toString()} helper="registros de análise" />
      <AuditMetric label="Ações humanas" value={feedbacks.length.toString()} helper="últimos eventos filtrados" />
      <AuditMetric label="Confiança média" value={`${average.toFixed(1)}%`} helper="nesta página" />
      <AuditMetric label="Alertas" value={failures.toString()} helper={`${realMode} execução(ões) fora do dry-run`} />
    </section>
  );
}

function AuditFilters({ filters }: { filters: Record<string, string> }) {
  return (
    <form
      className="panel p-4"
      onSubmit={(event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        router.get('/glpi-ai/audit', data, { preserveState: true, preserveScroll: true });
      }}
    >
      <div className="grid gap-3 xl:grid-cols-[1fr_170px_180px_150px_150px_auto_auto]">
        <label htmlFor="audit-search" className="min-w-0">
          <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Busca</span>
          <div className="flex items-center border border-slate-300 bg-slate-50 px-3 focus-within:border-[#214064] focus-within:bg-white focus-within:ring-2 focus-within:ring-[#214064]/15">
            <Search aria-hidden="true" size={16} className="shrink-0 text-slate-500" />
            <input
              id="audit-search"
              name="search"
              type="search"
              defaultValue={filters.search ?? ''}
              className="min-h-11 w-full bg-transparent px-3 py-2 text-sm font-semibold text-slate-950 placeholder:text-slate-400 focus-visible:outline-none"
              placeholder="Chamado, sugestão, técnico ou erro..."
              autoComplete="off"
            />
          </div>
        </label>

        <label htmlFor="audit-status">
          <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Status</span>
          <select id="audit-status" name="status" defaultValue={filters.status ?? ''} className="field">
            <option value="">Todos</option>
            <option value="completed">Concluída</option>
            <option value="failed">Falhou</option>
            <option value="running">Em execução</option>
          </select>
        </label>

        <label htmlFor="audit-action">
          <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Decisão/Ação</span>
          <select id="audit-action" name="action" defaultValue={filters.action ?? ''} className="field">
            <option value="">Todas</option>
            <option value="assign_to_technician">Sugerir técnico</option>
            <option value="manual_triage">Triagem manual</option>
            <option value="approve">Aprovação</option>
            <option value="reject">Rejeição</option>
            <option value="assign_other_technician">Outro técnico</option>
          </select>
        </label>

        <label htmlFor="audit-from">
          <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">De</span>
          <input id="audit-from" name="from" type="date" defaultValue={filters.from ?? ''} className="field" />
        </label>

        <label htmlFor="audit-to">
          <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Até</span>
          <input id="audit-to" name="to" type="date" defaultValue={filters.to ?? ''} className="field" />
        </label>

        <button type="submit" className="btn btn-primary mt-5">
          <Filter aria-hidden="true" size={16} />
          Filtrar
        </button>

        <Link href="/glpi-ai/audit" className="btn btn-secondary mt-5">
          <RotateCcw aria-hidden="true" size={16} />
          Limpar
        </Link>
      </div>
    </form>
  );
}

function RunCard({ run, glpiWebBaseUrl }: { run: AuditRun; glpiWebBaseUrl: string }) {
  const ticketUrl = glpiTicketUrl(glpiWebBaseUrl, run.glpi_ticket_id);
  const reason = run.final_decision?.reason || run.ai_decision?.reason || 'Sem justificativa registrada.';

  return (
    <article className="border-t border-slate-200 bg-white p-4 transition-colors hover:bg-slate-50">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Execução #{run.id}</p>
          <h3 className="mt-1 text-lg font-black text-slate-950">Chamado #{run.glpi_ticket_id}</h3>
          <p className="mt-1 text-sm font-semibold text-slate-600">{compactDate(run.created_at)} · Duração: {duration(run.duration_ms)}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <span className={`inline-flex border px-2.5 py-1 text-xs font-black uppercase tracking-wide ${statusTone(run.status)}`}>{labelStatus(run.status)}</span>
          <span className={`inline-flex border px-2.5 py-1 text-xs font-black uppercase tracking-wide ${riskTone(run.risk_level)}`}>{run.risk_level === 'high' ? 'Risco alto' : run.risk_level === 'medium' ? 'Risco médio' : 'Risco baixo'}</span>
          <ConfidenceBadge value={Number(run.confidence ?? 0)} />
        </div>
      </div>

      <div className="mt-4 grid gap-3 lg:grid-cols-3">
        <div className="border border-slate-200 bg-slate-50 p-3">
          <p className="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Decisão</p>
          <p className="mt-1 font-black text-slate-950">{labelAction(run.recommended_action)}</p>
        </div>
        <div className="border border-slate-200 bg-slate-50 p-3">
          <p className="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Recomendação</p>
          <p className="mt-1 font-black text-slate-950">{run.suggestion?.recommended_technician_name || run.suggestion?.recommended_group_name || '-'}</p>
        </div>
        <div className="border border-slate-200 bg-slate-50 p-3">
          <p className="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Modo</p>
          <p className="mt-1 font-black text-slate-950">{run.dry_run ? 'Dry-run' : 'Execução real'} · {run.auto_assign_enabled ? 'autoatribuição ligada' : 'autoatribuição desligada'}</p>
        </div>
      </div>

      <p className="mt-4 border-l-4 border-[#214064] bg-slate-50 p-3 text-sm leading-6 text-slate-700">{reason}</p>

      {run.error_message ? <p className="mt-3 border border-red-300 bg-red-50 p-3 text-sm font-semibold text-red-900">{run.error_message}</p> : null}

      <div className="mt-4 flex flex-wrap gap-2 text-sm">
        {run.suggestion?.id ? (
          <Link href={`/glpi-ai/suggestions/${run.suggestion.id}`} className="btn btn-secondary min-h-10 px-3">
            <History size={15} />
            Abrir sugestão
          </Link>
        ) : null}
        {ticketUrl ? (
          <a href={ticketUrl} target="_blank" rel="noreferrer" className="btn btn-secondary min-h-10 px-3">
            <ExternalLink size={15} />
            Abrir chamado no GLPI
          </a>
        ) : null}
      </div>

      <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-slate-500">
        <span>Algoritmo: {run.algorithm_version ?? '-'}</span>
        <span>Modelo IA: {run.model_used ?? '-'}</span>
        <span>Embedding: {run.embedding_model_used ?? '-'}</span>
      </div>
    </article>
  );
}

function FeedbackCard({ item, glpiWebBaseUrl }: { item: HumanFeedback; glpiWebBaseUrl: string }) {
  const ticketUrl = glpiTicketUrl(glpiWebBaseUrl, item.suggestion?.glpi_ticket_id);

  return (
    <article className="border-t border-slate-200 bg-white p-4 transition-colors hover:bg-slate-50">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Ação humana #{item.id}</p>
          <h3 className="mt-1 font-black text-slate-950">{labelAction(item.action)}</h3>
          <p className="mt-1 text-sm font-semibold text-slate-600">
            {labelStatus(item.previous_status)} para {labelStatus(item.new_status)} · {compactDate(item.created_at)}
          </p>
        </div>
        <span className={`inline-flex border px-2.5 py-1 text-xs font-black uppercase tracking-wide ${statusTone(item.new_status)}`}>{labelStatus(item.new_status)}</span>
      </div>

      <div className="mt-3 grid gap-3 text-sm md:grid-cols-3">
        <p><strong>Usuário:</strong> #{item.user_id ?? '-'}</p>
        <p><strong>Sugestão:</strong> #{item.assignment_suggestion_id ?? '-'}</p>
        <p><strong>Chamado:</strong> #{item.suggestion?.glpi_ticket_id ?? '-'}</p>
      </div>

      {item.observation ? (
        <p className="mt-3 border-l-4 border-[#214064] bg-slate-50 p-3 text-sm leading-6 text-slate-700">{item.observation}</p>
      ) : (
        <p className="mt-3 text-sm font-semibold text-slate-500">Sem observação registrada.</p>
      )}

      <div className="mt-4 flex flex-wrap gap-2 text-sm">
        {item.assignment_suggestion_id ? (
          <Link href={`/glpi-ai/suggestions/${item.assignment_suggestion_id}`} className="btn btn-secondary min-h-10 px-3">
            <History size={15} />
            Abrir sugestão
          </Link>
        ) : null}
        {ticketUrl ? (
          <a href={ticketUrl} target="_blank" rel="noreferrer" className="btn btn-secondary min-h-10 px-3">
            <ExternalLink size={15} />
            Abrir chamado no GLPI
          </a>
        ) : null}
      </div>
    </article>
  );
}

function AuditTabs({
  activeTab,
  onChange,
  runsCount,
  feedbacksCount,
}: {
  activeTab: 'runs' | 'feedbacks';
  onChange: (tab: 'runs' | 'feedbacks') => void;
  runsCount: number;
  feedbacksCount: number;
}) {
  const tabs = [
    {
      id: 'runs' as const,
      label: 'Execuções do robô',
      count: runsCount,
      icon: Activity,
      description: 'Análises, modelos, confiança, erros e decisão final.',
    },
    {
      id: 'feedbacks' as const,
      label: 'Ações humanas',
      count: feedbacksCount,
      icon: UserCheck,
      description: 'Aprovações, rejeições, escolhas manuais e observações.',
    },
  ];

  return (
    <div className="panel overflow-hidden">
      <div className="grid border-b border-slate-200 bg-slate-50 md:grid-cols-2" role="tablist" aria-label="Tipos de eventos de auditoria">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          const active = activeTab === tab.id;

          return (
            <button
              key={tab.id}
              type="button"
              role="tab"
              aria-selected={active}
              onClick={() => onChange(tab.id)}
              className={`flex min-h-20 items-start gap-3 border-b px-4 py-3 text-left transition-colors md:border-b-0 md:border-r last:md:border-r-0 ${
                active
                  ? 'border-[#214064] bg-white text-[#0e2a49] shadow-[inset_0_3px_0_#214064]'
                  : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-white hover:text-slate-950'
              }`}
            >
              <span className={`mt-1 grid size-9 shrink-0 place-items-center border ${active ? 'border-[#214064] bg-[#214064] text-white' : 'border-slate-200 bg-white text-slate-500'}`}>
                <Icon size={17} />
              </span>
              <span className="min-w-0">
                <span className="flex flex-wrap items-center gap-2">
                  <span className="font-black">{tab.label}</span>
                  <span className={`border px-2 py-0.5 text-[11px] font-black tabular-nums ${active ? 'border-[#214064]/30 bg-[#eef4fb] text-[#214064]' : 'border-slate-200 bg-white text-slate-500'}`}>
                    {tab.count}
                  </span>
                </span>
                <span className="mt-1 block text-xs font-semibold leading-5 text-slate-500">{tab.description}</span>
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

export default function AuditIndex({
  runs,
  feedbacks,
  filters,
  glpiWebBaseUrl,
  dryRun,
  autoAssign,
}: {
  runs: PaginatedRuns;
  feedbacks: HumanFeedback[];
  filters: Record<string, string>;
  glpiWebBaseUrl: string;
  dryRun: boolean;
  autoAssign: boolean;
}) {
  const [activeTab, setActiveTab] = useState<'runs' | 'feedbacks'>('runs');

  return (
    <GlpiAiLayout title="Auditoria" dryRun={dryRun} autoAssign={autoAssign}>
      <Head title="Auditoria | GLPI BOT" />

      <div className="space-y-4">
        <AuditFilters filters={filters} />

        <AuditTabs activeTab={activeTab} onChange={setActiveTab} runsCount={runs.total} feedbacksCount={feedbacks.length} />

        {activeTab === 'runs' ? (
          <section className="panel overflow-hidden" role="tabpanel" aria-label="Execuções do robô">
            <div className="panel-header flex items-center justify-between gap-3">
              <div className="flex items-center gap-2">
                <Activity size={17} />
                <h2 className="section-title">Execuções do robô</h2>
              </div>
              <span className="text-xs font-black uppercase tracking-[0.14em] text-slate-500">{runs.total} registro(s)</span>
            </div>

            {(runs.data ?? []).length === 0 ? (
              <p className="p-6 text-sm font-semibold text-slate-500">Nenhuma execução encontrada com os filtros atuais.</p>
            ) : (
              runs.data.map((run) => <RunCard key={run.id} run={run} glpiWebBaseUrl={glpiWebBaseUrl} />)
            )}

            <Pagination runs={runs} />
          </section>
        ) : (
          <section className="panel overflow-hidden" role="tabpanel" aria-label="Ações humanas">
            <div className="panel-header flex items-center justify-between gap-3">
              <div className="flex items-center gap-2">
                <UserCheck size={17} />
                <h2 className="section-title">Ações humanas</h2>
              </div>
              <span className="text-xs font-black uppercase tracking-[0.14em] text-slate-500">{feedbacks.length} evento(s)</span>
            </div>

            {feedbacks.length === 0 ? (
              <p className="p-6 text-sm font-semibold text-slate-500">Nenhuma ação humana encontrada com os filtros atuais.</p>
            ) : (
              feedbacks.map((item) => <FeedbackCard key={item.id} item={item} glpiWebBaseUrl={glpiWebBaseUrl} />)
            )}
          </section>
        )}
      </div>
    </GlpiAiLayout>
  );
}
