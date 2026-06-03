import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ClipboardList, ExternalLink, Filter, RotateCcw, Search, UserCheck, UsersRound } from 'lucide-react';
import { ConfidenceBadge, RiskBadge, StatusBadge } from '../../../Components/GlpiAi/Badges';
import { GlpiAiLayout } from '../../../Layouts/GlpiAiLayout';
import type { Suggestion } from '../../../Types/glpi-ai';

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedSuggestions {
  data: Suggestion[];
  current_page: number;
  from: number | null;
  last_page: number;
  links: PaginationLink[];
  per_page: number;
  to: number | null;
  total: number;
}

const actionLabels: Record<string, string> = {
  assign_to_technician: 'Técnico',
  assign_to_group: 'Grupo',
  manual_triage: 'Triagem manual',
};

const decisionMeta: Record<string, { icon: typeof UserCheck; tone: string; title: string }> = {
  assign_to_technician: {
    icon: UserCheck,
    title: 'Técnico',
    tone: 'border-[#214064] bg-[#eef4fb] text-[#0e2a49]',
  },
  assign_to_group: {
    icon: UsersRound,
    title: 'Grupo',
    tone: 'border-[#c9b27b] bg-[#fff8e7] text-[#583c00]',
  },
  manual_triage: {
    icon: ClipboardList,
    title: 'Triagem',
    tone: 'border-slate-300 bg-slate-100 text-slate-700',
  },
};

function labelForAction(action: string) {
  return actionLabels[action] ?? action;
}

function compactDate(value?: string) {
  if (!value) return '-';

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

function glpiTicketUrl(baseUrl: string, ticketId?: number | string) {
  if (!ticketId) return undefined;
  return `${baseUrl.replace(/\/$/, '')}/front/ticket.form.php?id=${ticketId}`;
}

function readablePageLabel(label: string) {
  if (label.includes('Previous')) return 'Anterior';
  if (label.includes('Next')) return 'Próxima';

  return label.replace('&laquo;', '').replace('&raquo;', '').trim();
}

function Pagination({ suggestions }: { suggestions: PaginatedSuggestions }) {
  if (suggestions.last_page <= 1) return null;

  const previous = suggestions.links.find((link) => link.label.includes('Previous'));
  const next = suggestions.links.find((link) => link.label.includes('Next'));
  const pages = suggestions.links.filter((link) => !link.label.includes('Previous') && !link.label.includes('Next'));

  return (
    <nav className="mt-4 flex flex-col gap-3 border border-slate-200 bg-white px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between" aria-label="Paginação das sugestões">
      <p className="font-semibold text-slate-600">
        Mostrando <span className="font-black text-slate-950">{suggestions.from ?? 0}</span> a <span className="font-black text-slate-950">{suggestions.to ?? 0}</span> de{' '}
        <span className="font-black text-slate-950">{suggestions.total}</span> sugestões
      </p>

      <div className="flex flex-wrap items-center gap-1.5">
        <PageButton link={previous} fallbackLabel="Anterior" icon="previous" />

        <div className="hidden items-center gap-1 sm:flex">
          {pages.map((link) => (
            <PageButton key={`${link.label}-${link.url ?? 'disabled'}`} link={link} fallbackLabel={readablePageLabel(link.label)} />
          ))}
        </div>

        <span className="px-2 py-2 font-black text-slate-700 sm:hidden">
          Página {suggestions.current_page}/{suggestions.last_page}
        </span>

        <PageButton link={next} fallbackLabel="Próxima" icon="next" />
      </div>
    </nav>
  );
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
  const classes = `inline-flex min-h-10 items-center justify-center gap-1.5 border px-3 font-black transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#214064] focus-visible:ring-offset-2 ${
    link?.active
      ? 'border-[#214064] bg-[#214064] text-white'
      : disabled
        ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-300'
        : 'border-slate-200 bg-white text-slate-700 hover:border-[#214064] hover:text-[#214064]'
  }`;

  if (disabled) {
    return (
      <span className={classes} aria-disabled="true">
        {content}
      </span>
    );
  }

  return (
    <Link href={link.url as string} preserveScroll preserveState className={classes}>
      {content}
    </Link>
  );
}

function SummaryStrip({ suggestions, rows }: { suggestions: PaginatedSuggestions; rows: Suggestion[] }) {
  const pending = rows.filter((item) => item.status === 'pending').length;
  const technician = rows.filter((item) => item.recommended_action === 'assign_to_technician').length;
  const manual = rows.filter((item) => item.recommended_action === 'manual_triage').length;
  const average = rows.length > 0 ? rows.reduce((sum, item) => sum + Number(item.confidence ?? 0), 0) / rows.length : 0;

  return (
    <section className="mb-4 grid gap-3 md:grid-cols-4" aria-label="Resumo da fila">
      <QueueMetric label="Total filtrado" value={suggestions.total.toString()} helper="resultado atual" />
      <QueueMetric label="Pendentes nesta página" value={pending.toString()} helper="aguardando decisão" />
      <QueueMetric label="Sugere técnico" value={technician.toString()} helper={`${manual} triagem manual`} />
      <QueueMetric label="Confiança média" value={`${average.toFixed(1)}%`} helper="nesta página" />
    </section>
  );
}

function QueueMetric({ label, value, helper }: { label: string; value: string; helper: string }) {
  return (
    <div className="panel p-4">
      <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-black tabular-nums text-slate-950">{value}</p>
      <p className="mt-1 text-xs font-semibold text-slate-500">{helper}</p>
    </div>
  );
}

function SuggestionRow({ item, glpiWebBaseUrl }: { item: Suggestion; glpiWebBaseUrl: string }) {
  const meta = decisionMeta[item.recommended_action] ?? decisionMeta.manual_triage;
  const Icon = meta.icon;
  const ticketUrl = glpiTicketUrl(glpiWebBaseUrl, item.glpi_ticket_id);

  return (
    <tr className="border-t border-slate-200 bg-white transition-colors hover:bg-[#f6f8fb]">
      <td className="w-24 p-4 align-top">
        <Link href={`/glpi-ai/suggestions/${item.id}`} className="font-black text-[#214064] underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#214064] focus-visible:ring-offset-2">
          #{item.glpi_ticket_id}
        </Link>
        <p className="mt-1 text-xs font-semibold tabular-nums text-slate-500">{compactDate(item.created_at)}</p>
        {ticketUrl ? (
          <a href={ticketUrl} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-xs font-black text-slate-500 underline-offset-4 hover:text-[#214064] hover:underline">
            <ExternalLink size={13} />
            GLPI
          </a>
        ) : null}
      </td>

      <td className="min-w-0 p-4 align-top">
        <Link
          href={`/glpi-ai/suggestions/${item.id}`}
          className="block max-w-4xl text-pretty font-black leading-snug text-slate-950 underline-offset-4 hover:text-[#214064] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#214064] focus-visible:ring-offset-2"
        >
          <span className="line-clamp-2 break-words">{item.title || 'Chamado sem titulo'}</span>
        </Link>
        <p className="mt-1 line-clamp-1 text-xs font-semibold text-slate-500">{item.category_name || 'Sem categoria detectada'}</p>
      </td>

      <td className="w-44 p-4 align-top">
        <span className={`inline-flex items-center gap-2 border px-2.5 py-1.5 text-xs font-black uppercase tracking-wide ${meta.tone}`}>
          <Icon aria-hidden="true" size={15} />
          {meta.title}
        </span>
      </td>

      <td className="w-52 p-4 align-top">
        <p className="font-bold text-slate-950">{item.recommended_technician_name || '-'}</p>
        <p className="mt-1 line-clamp-1 text-xs font-semibold text-slate-500">{item.recommended_group_name || '-'}</p>
      </td>

      <td className="w-32 p-4 align-top">
        <ConfidenceBadge value={item.confidence} />
      </td>

      <td className="w-28 p-4 align-top">
        <RiskBadge risk={item.risk_level} />
      </td>

      <td className="w-36 p-4 align-top">
        <StatusBadge status={item.status} />
      </td>
    </tr>
  );
}

function MobileSuggestionCard({ item }: { item: Suggestion }) {
  const meta = decisionMeta[item.recommended_action] ?? decisionMeta.manual_triage;
  const Icon = meta.icon;

  return (
    <Link
      href={`/glpi-ai/suggestions/${item.id}`}
      className="block border border-slate-200 bg-white p-4 transition-colors hover:border-[#214064] hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#214064] focus-visible:ring-offset-2"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="font-black text-[#214064]">#{item.glpi_ticket_id}</p>
          <h2 className="mt-1 line-clamp-3 break-words text-base font-black leading-snug text-slate-950">{item.title || 'Chamado sem titulo'}</h2>
          <p className="mt-1 line-clamp-1 text-xs font-semibold text-slate-500">{item.category_name || 'Sem categoria detectada'}</p>
        </div>
        <ConfidenceBadge value={item.confidence} />
      </div>

      <div className="mt-4 grid gap-2 text-sm">
        <div className={`inline-flex w-fit items-center gap-2 border px-2.5 py-1.5 text-xs font-black uppercase tracking-wide ${meta.tone}`}>
          <Icon aria-hidden="true" size={15} />
          {labelForAction(item.recommended_action)}
        </div>
        <p>
          <span className="font-black text-slate-500">Técnico:</span> {item.recommended_technician_name || '-'}
        </p>
        <p>
          <span className="font-black text-slate-500">Grupo:</span> {item.recommended_group_name || '-'}
        </p>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        <RiskBadge risk={item.risk_level} />
        <StatusBadge status={item.status} />
      </div>
    </Link>
  );
}

export default function SuggestionsIndex({ suggestions, filters, dryRun, glpiWebBaseUrl }: { suggestions: PaginatedSuggestions; filters: Record<string, string>; dryRun: boolean; glpiWebBaseUrl: string }) {
  const rows = suggestions.data ?? [];

  return (
    <GlpiAiLayout title="Fila de Sugestões" dryRun={dryRun}>
      <Head title="Sugestões | GLPI BOT" />

      <SummaryStrip suggestions={suggestions} rows={rows} />

      <form
        className="panel mb-4 p-4"
        onSubmit={(event) => {
          event.preventDefault();
          const data = Object.fromEntries(new FormData(event.currentTarget));
          router.get('/glpi-ai/suggestions', data, { preserveState: true, preserveScroll: true });
        }}
      >
        <div className="grid gap-3 lg:grid-cols-[1fr_170px_170px_170px_auto_auto]">
          <label htmlFor="suggestion-search" className="min-w-0">
            <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Busca</span>
            <div className="flex items-center border border-slate-300 bg-slate-50 px-3 focus-within:border-[#214064] focus-within:bg-white focus-within:ring-2 focus-within:ring-[#214064]/15">
              <Search aria-hidden="true" size={16} className="shrink-0 text-slate-500" />
              <input
                id="suggestion-search"
                name="search"
                type="search"
                defaultValue={filters.search ?? ''}
                className="min-h-11 w-full bg-transparent px-3 py-2 text-sm font-semibold text-slate-950 placeholder:text-slate-400 focus-visible:outline-none"
                placeholder="Título ou ID do chamado…"
                autoComplete="off"
              />
            </div>
          </label>

          <label htmlFor="suggestion-status">
            <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Status</span>
            <select id="suggestion-status" name="status" defaultValue={filters.status ?? ''} className="field">
              <option value="">Todos</option>
              <option value="pending">Pendente</option>
              <option value="accepted">Aprovada</option>
              <option value="rejected">Rejeitada</option>
              <option value="manual_triage">Triagem manual</option>
              <option value="glpi_closed">Finalizada no GLPI</option>
              <option value="failed">Falha</option>
            </select>
          </label>

          <label htmlFor="suggestion-action">
            <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Decisão</span>
            <select id="suggestion-action" name="action" defaultValue={filters.action ?? ''} className="field">
              <option value="">Todas</option>
              <option value="assign_to_technician">Técnico</option>
              <option value="assign_to_group">Grupo</option>
              <option value="manual_triage">Triagem manual</option>
            </select>
          </label>

          <label htmlFor="suggestion-risk">
            <span className="mb-1.5 block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Risco</span>
            <select id="suggestion-risk" name="risk" defaultValue={filters.risk ?? ''} className="field">
              <option value="">Todos</option>
              <option value="low">Baixo</option>
              <option value="medium">Médio</option>
              <option value="high">Alto</option>
            </select>
          </label>

          <button type="submit" className="btn btn-primary mt-5">
            <Filter aria-hidden="true" size={16} />
            Filtrar
          </button>

          <Link
            href="/glpi-ai/suggestions"
            className="btn btn-secondary mt-5"
          >
            <RotateCcw aria-hidden="true" size={16} />
            Limpar
          </Link>
        </div>
      </form>

      <section className="panel overflow-hidden" aria-label="Sugestões encontradas">
        <div className="hidden overflow-x-auto lg:block">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-100 text-[11px] uppercase tracking-[0.16em] text-slate-500">
              <tr>
                <th className="p-4">GLPI</th>
                <th className="p-4">Chamado</th>
                <th className="p-4">Decisão</th>
                <th className="p-4">Responsável</th>
                <th className="p-4">Confiança</th>
                <th className="p-4">Risco</th>
                <th className="p-4">Status</th>
              </tr>
            </thead>
            <tbody>{rows.map((item) => <SuggestionRow key={item.id} item={item} glpiWebBaseUrl={glpiWebBaseUrl} />)}</tbody>
          </table>
        </div>

        <div className="grid gap-3 p-3 lg:hidden">
          {rows.map((item) => <MobileSuggestionCard key={item.id} item={item} />)}
        </div>

        {rows.length === 0 ? (
          <div className="px-6 py-14 text-center">
            <div className="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-500">
              <ClipboardList aria-hidden="true" size={22} />
            </div>
            <h2 className="mt-4 text-lg font-black text-slate-950">Nenhuma sugestão encontrada</h2>
            <p className="mx-auto mt-1 max-w-md text-sm font-semibold text-slate-500">Ajuste os filtros ou limpe a busca para voltar à fila completa.</p>
          </div>
        ) : null}
      </section>

      <Pagination suggestions={suggestions} />
    </GlpiAiLayout>
  );
}
