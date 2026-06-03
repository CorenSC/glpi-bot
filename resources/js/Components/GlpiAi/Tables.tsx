import { ConfidenceBadge, RiskBadge, StatusBadge } from './Badges';
import type { SimilarTicket, Suggestion, TechnicianScore } from '../../Types/glpi-ai';

function score(value: number) {
  return Number(value ?? 0).toFixed(3);
}

function feedbackLabel(item: TechnicianScore) {
  const metadata = item.metadata ?? {};
  const total = Number(metadata.human_feedback_total ?? 0);
  const adjustment = Number(metadata.human_feedback_adjustment ?? 0);

  if (total === 0) return '-';

  const sign = adjustment > 0 ? '+' : '';
  return `${sign}${adjustment.toFixed(1)} pts (${total})`;
}

function contextLabel(item: TechnicianScore) {
  const value = Number(item.context_score ?? item.metadata?.context_score ?? 0);
  const adjustment = Number(item.metadata?.context_adjustment ?? 0);
  const sign = adjustment > 0 ? '+' : '';

  return `${value.toFixed(3)} (${sign}${adjustment.toFixed(1)} pts)`;
}

function evidenceLabel(item: TechnicianScore) {
  const share = Number(item.metadata?.evidence_share ?? 0) * 100;
  const topCount = Number(item.metadata?.top_evidence_count ?? 0);
  const adjustment = Number(item.metadata?.dominance_adjustment ?? 0);
  const sign = adjustment > 0 ? '+' : '';

  return `${share.toFixed(0)}% / top ${topCount} (${sign}${adjustment.toFixed(1)} pts)`;
}

const head = 'table-head';
const row = 'table-row';

export function SuggestionsTable({ suggestions }: { suggestions: Suggestion[] }) {
  return (
    <div className="overflow-x-auto border border-slate-200 bg-white">
      <table className="min-w-full text-left text-sm">
        <thead className={head}>
          <tr>
            <th className="p-3">GLPI</th>
            <th className="p-3">Título</th>
            <th className="p-3">Técnico</th>
            <th className="p-3">Grupo</th>
            <th className="p-3">Confiança</th>
            <th className="p-3">Risco</th>
            <th className="p-3">Status</th>
          </tr>
        </thead>
        <tbody>
          {suggestions.map((item) => (
            <tr key={item.id} className={row}>
              <td className="p-3 font-black">#{item.glpi_ticket_id}</td>
              <td className="max-w-md p-3 font-semibold">{item.title}</td>
              <td className="p-3">{item.recommended_technician_name ?? '-'}</td>
              <td className="p-3">{item.recommended_group_name ?? '-'}</td>
              <td className="p-3"><ConfidenceBadge value={item.confidence} /></td>
              <td className="p-3"><RiskBadge risk={item.risk_level} /></td>
              <td className="p-3"><StatusBadge status={item.status} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function TechnicianRankingTable({ scores = [] }: { scores?: TechnicianScore[] }) {
  return (
    <div className="overflow-x-auto border border-slate-200 bg-white">
      <table className="min-w-full text-left text-sm">
        <thead className={head}>
          <tr>
            <th className="p-3">Rank</th>
            <th className="p-3">Técnico</th>
            <th className="p-3">Final</th>
            <th className="p-3">Texto</th>
            <th className="p-3">Categoria</th>
            <th className="p-3">Contexto</th>
            <th className="p-3">Evidencia</th>
            <th className="p-3">Historico</th>
            <th className="p-3">Recencia</th>
            <th className="p-3">Carga</th>
            <th className="p-3">Feedback</th>
            <th className="p-3">Bloqueio</th>
          </tr>
        </thead>
        <tbody>
          {scores.map((item) => (
            <tr key={item.id} className={row}>
              <td className="p-3 font-black">{item.rank_position}</td>
              <td className="p-3">
                <p className="font-black">{item.technician_name ?? '-'}</p>
                <p className="text-xs text-[var(--glpi-muted)]">{item.group_name ?? '-'}</p>
              </td>
              <td className="p-3"><ConfidenceBadge value={item.final_score} /></td>
              <td className="p-3 font-mono text-xs">{score(item.text_similarity_score)}</td>
              <td className="p-3 font-mono text-xs">{score(item.category_score)}</td>
              <td className="p-3 font-mono text-xs">{contextLabel(item)}</td>
              <td className="p-3 font-mono text-xs">{evidenceLabel(item)}</td>
              <td className="p-3 font-mono text-xs">{score(item.history_score)}</td>
              <td className="p-3 font-mono text-xs">{score(item.recency_score)}</td>
              <td className="p-3 font-mono text-xs">{score(item.workload_score)}</td>
              <td className="p-3 text-xs font-black">{feedbackLabel(item)}</td>
              <td className="p-3 text-xs">{item.is_blocked ? item.blocked_reason ?? 'bloqueado' : '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function SimilarTicketsTable({ tickets = [] }: { tickets?: SimilarTicket[] }) {
  return (
    <div className="overflow-x-auto border border-slate-200 bg-white">
      <table className="min-w-full text-left text-sm">
        <thead className={head}>
          <tr>
            <th className="p-3">GLPI</th>
            <th className="p-3">Referencia</th>
            <th className="p-3">Score</th>
            <th className="p-3">Solucionador</th>
            <th className="p-3">Grupo</th>
          </tr>
        </thead>
        <tbody>
          {tickets.map((ticket) => (
            <tr key={ticket.id} className={row}>
              <td className="p-3 font-black">#{ticket.glpi_ticket_id}</td>
              <td className="max-w-3xl p-3 font-semibold">{ticket.title}</td>
              <td className="p-3 font-mono text-xs font-black">{score(ticket.final_similarity_score)}</td>
              <td className="p-3">{ticket.solver_technician_name ?? ticket.assigned_technician_name ?? '-'}</td>
              <td className="p-3 text-xs">{ticket.assigned_group_name ?? '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
