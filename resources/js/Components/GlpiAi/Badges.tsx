import type { RiskLevel, SuggestionStatus } from '../../Types/glpi-ai';

const statusLabels: Record<string, string> = {
  pending: 'Pendente',
  accepted: 'Aprovada',
  rejected: 'Rejeitada',
  auto_assigned: 'Autoatribuída',
  manual_triage: 'Triagem manual',
  failed: 'Falha',
  ignored: 'Ignorada',
  glpi_closed: 'Finalizada no GLPI',
};

const riskLabels: Record<string, string> = {
  low: 'Baixo',
  medium: 'Médio',
  high: 'Alto',
};

export function StatusBadge({ status }: { status: SuggestionStatus | string }) {
  const tone = status === 'pending'
    ? 'border-amber-200 bg-amber-50 text-amber-900'
    : status === 'failed' || status === 'rejected'
      ? 'border-red-200 bg-red-50 text-red-900'
      : status === 'accepted' || status === 'auto_assigned' || status === 'glpi_closed'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : status === 'manual_triage'
          ? 'border-slate-200 bg-slate-100 text-slate-700'
          : 'border-blue-200 bg-blue-50 text-[#214064]';

  return <span className={`inline-flex rounded-md border px-2.5 py-1 text-xs font-black uppercase tracking-wide ${tone}`}>{statusLabels[status] ?? status}</span>;
}

export function RiskBadge({ risk }: { risk: RiskLevel | string }) {
  const tone = risk === 'high'
    ? 'border-red-200 bg-red-50 text-red-900'
    : risk === 'medium'
      ? 'border-amber-200 bg-amber-50 text-amber-900'
      : 'border-emerald-200 bg-emerald-50 text-emerald-800';

  return <span className={`inline-flex rounded-md border px-2.5 py-1 text-xs font-black uppercase tracking-wide ${tone}`}>{riskLabels[risk] ?? risk}</span>;
}

export function ConfidenceBadge({ value }: { value: number }) {
  const numeric = Number(value);
  const tone = numeric >= 85
    ? 'bg-[#214064] text-white'
    : numeric >= 65
      ? 'bg-amber-500 text-white'
      : 'bg-slate-200 text-slate-800';

  return <span className={`inline-flex min-w-16 justify-center rounded-md px-2.5 py-1 text-xs font-black ${tone}`}>{numeric.toFixed(1)}%</span>;
}
