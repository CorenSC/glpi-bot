import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, ChevronDown, ExternalLink, FileText, History, RotateCcw, Send, Sparkles, UserCheck, Users, X } from 'lucide-react';
import { ConfidenceBadge, RiskBadge, StatusBadge } from '../../../Components/GlpiAi/Badges';
import { SimilarTicketsTable, TechnicianRankingTable } from '../../../Components/GlpiAi/Tables';
import { GlpiAiLayout } from '../../../Layouts/GlpiAiLayout';
import type { Suggestion, TechnicianScore } from '../../../Types/glpi-ai';

const canonicalLabels = [
  'Titulo',
  'Categoria detectada no titulo',
  'Categoria informada',
  'Descricao',
  'Solucao',
  'Grupo atribuido',
  'Tecnico atribuido',
  'Tecnico solucionador',
  'Historico resumido',
];

function actionLabel(action: string) {
  if (action === 'assign_to_technician') return 'Sugerir técnico';
  if (action === 'assign_to_group') return 'Sugerir grupo';
  return 'Triagem manual';
}

function actionTone(action: string) {
  if (action === 'assign_to_technician') return 'border-[#214064] bg-[#eef4fb] text-[#0e2a49]';
  if (action === 'assign_to_group') return 'border-amber-300 bg-amber-50 text-amber-900';
  return 'border-slate-300 bg-slate-100 text-slate-800';
}

function glpiTicketUrl(baseUrl: string, ticketId?: number | string) {
  if (!ticketId) return undefined;
  return `${baseUrl.replace(/\/$/, '')}/front/ticket.form.php?id=${ticketId}`;
}

function canonicalValue(text: string | undefined, label: string) {
  if (!text) return '';
  const line = text.split('\n').find((item) => item.toLowerCase().startsWith(label.toLowerCase() + ':'));
  return line?.slice(label.length + 1).trim() ?? '';
}

function canonicalSection(text: string | undefined, label: string) {
  if (!text) return '';
  const start = `${label}:`;
  const lines = text.split('\n');
  const startIndex = lines.findIndex((item) => item.toLowerCase().startsWith(start.toLowerCase()));
  if (startIndex === -1) return '';

  const collected = [lines[startIndex].slice(start.length).trim()];
  for (let index = startIndex + 1; index < lines.length; index += 1) {
    const isNextLabel = canonicalLabels.some((candidate) => lines[index].toLowerCase().startsWith(`${candidate}:`.toLowerCase()));
    if (isNextLabel) break;
    collected.push(lines[index]);
  }

  return collected.join('\n').trim();
}

function auditSentences(suggestion: Suggestion) {
  const run = suggestion.analysis_run;
  const scores = run?.technician_scores ?? [];
  const similar = run?.similar_tickets ?? [];
  const top = scores[0];
  const second = scores[1];
  const ai = (run?.ai_decision ?? {}) as Record<string, any>;
  const final = (run?.final_decision ?? {}) as Record<string, any>;

  return [
    top ? `Melhor candidato: ${top.technician_name ?? 'técnico sem nome'} com ${Number(top.final_score).toFixed(1)}%.` : 'Nenhum técnico entrou no ranking final.',
    second ? `Segundo candidato: ${second.technician_name ?? 'técnico sem nome'} com ${Number(second.final_score).toFixed(1)}%.` : '',
    `Base usada: ${similar.length} chamado(s) similar(es).`,
    ai.reason ? `Validação da IA: ${ai.reason}` : '',
    final.dry_run ? 'Dry-run ativo: nenhuma alteração real será feita no GLPI.' : '',
  ].filter(Boolean);
}

function scoreGap(scores: TechnicianScore[]) {
  if (scores.length < 2) return null;
  return Math.abs(Number(scores[0]?.final_score ?? 0) - Number(scores[1]?.final_score ?? 0));
}

function MiniStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="border border-slate-200 bg-slate-50 px-3 py-2">
      <p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">{label}</p>
      <p className="mt-1 font-black text-slate-950">{value}</p>
    </div>
  );
}

function EvidenceBlock({ title, count, defaultOpen, children }: { title: string; count?: number; defaultOpen?: boolean; children: React.ReactNode }) {
  return (
    <details className="panel group" open={defaultOpen}>
      <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
        <div>
          <h3 className="font-black text-slate-950">{title}</h3>
          {typeof count === 'number' ? <p className="mt-0.5 text-xs font-semibold text-slate-500">{count} registro(s)</p> : null}
        </div>
        <ChevronDown className="text-slate-500 transition group-open:rotate-180" size={18} />
      </summary>
      <div className="border-t border-slate-200 p-4">{children}</div>
    </details>
  );
}

export default function SuggestionShow({ suggestion, dryRun, autoAssign, glpiWebBaseUrl }: { suggestion: Suggestion; dryRun: boolean; autoAssign: boolean; glpiWebBaseUrl: string }) {
  const form = useForm({ observation: '' });
  const run = suggestion.analysis_run;
  const scores = run?.technician_scores ?? [];
  const similarTickets = run?.similar_tickets ?? [];
  const hasTechnician = Boolean(suggestion.recommended_technician_name || suggestion.recommended_technician_id);
  const hasGroup = Boolean(suggestion.recommended_group_name || suggestion.recommended_group_id);
  const title = canonicalValue(run?.canonical_text, 'Titulo') || suggestion.title || '-';
  const category = canonicalValue(run?.canonical_text, 'Categoria detectada no titulo') || suggestion.category_name || '-';
  const description = canonicalSection(run?.canonical_text, 'Descricao');
  const audit = auditSentences(suggestion);
  const gap = scoreGap(scores);
  const closeTechnicians = scores.slice(0, 4).filter((score, index) => index === 0 || Math.abs(Number(scores[0]?.final_score ?? 0) - Number(score.final_score ?? 0)) <= 5);
  const recommendedPerson = hasTechnician ? suggestion.recommended_technician_name ?? `ID ${suggestion.recommended_technician_id}` : 'Nenhum técnico recomendado';
  const recommendedGroup = hasGroup ? suggestion.recommended_group_name ?? `ID ${suggestion.recommended_group_id}` : 'Nenhum grupo';
  const ticketUrl = glpiTicketUrl(glpiWebBaseUrl, suggestion.glpi_ticket_id);

  function post(path: string) {
    form.post(path, { preserveScroll: true });
  }

  function postWithSelectedTechnician(path: string) {
    const selected = document.querySelector<HTMLSelectElement>('#selected-technician-id')?.value;
    router.post(path, {
      observation: form.data.observation,
      technician_id: selected || suggestion.recommended_technician_id,
    }, { preserveScroll: true });
  }

  return (
    <GlpiAiLayout title={`Chamado #${suggestion.glpi_ticket_id}`} dryRun={dryRun} autoAssign={autoAssign}>
      <Head title={`Sugestão #${suggestion.id} | GLPI BOT`} />

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <Link href="/glpi-ai/suggestions" className="link-action inline-flex items-center gap-2 text-sm">
          <ArrowLeft size={16} />
          Voltar para a fila
        </Link>
        {ticketUrl ? (
          <a href={ticketUrl} target="_blank" rel="noreferrer" className="btn btn-secondary min-h-10 px-3">
            <ExternalLink size={16} />
            Abrir chamado no GLPI
          </a>
        ) : null}
      </div>

      <section className="grid gap-5 xl:grid-cols-[1fr_360px]">
        <div className="space-y-5">
          <section className="panel overflow-hidden">
            <div className="border-b border-slate-200 bg-[#214064] p-5 text-white">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <p className="text-[11px] font-black uppercase tracking-[0.18em] text-white/60">Decisão recomendada</p>
                  <h2 className="mt-2 text-3xl font-black tracking-tight">{actionLabel(suggestion.recommended_action)}</h2>
                  <p className="mt-2 max-w-4xl text-sm leading-6 text-white/75">{suggestion.reason || 'Sem justificativa registrada.'}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <StatusBadge status={suggestion.status} />
                  <RiskBadge risk={suggestion.risk_level} />
                  <ConfidenceBadge value={suggestion.confidence} />
                </div>
              </div>
            </div>

            <div className="grid gap-3 p-4 md:grid-cols-3">
              <div className={`border p-4 ${hasTechnician ? 'border-[#214064] bg-[#eef4fb]' : 'border-slate-200 bg-white'}`}>
                <div className="flex items-center gap-2 text-[11px] font-black uppercase tracking-wide text-slate-500">
                  <UserCheck size={15} />
                  Técnico
                </div>
                <p className="mt-2 text-xl font-black text-slate-950">{recommendedPerson}</p>
                <p className="mt-1 text-sm font-semibold text-slate-600">{hasTechnician ? 'Principal responsável sugerido.' : 'Sem segurança para indicar pessoa.'}</p>
              </div>

              <div className="border border-slate-200 bg-white p-4">
                <div className="flex items-center gap-2 text-[11px] font-black uppercase tracking-wide text-slate-500">
                  <Users size={15} />
                  Grupo
                </div>
                <p className="mt-2 text-xl font-black text-slate-950">{recommendedGroup}</p>
                <p className="mt-1 text-sm font-semibold text-slate-600">No seu fluxo, grupo é contexto.</p>
              </div>

              <div className={`border p-4 ${actionTone(suggestion.recommended_action)}`}>
                <div className="flex items-center gap-2 text-[11px] font-black uppercase tracking-wide opacity-70">
                  <AlertTriangle size={15} />
                  Operação
                </div>
                <p className="mt-2 text-xl font-black">{dryRun ? 'Apenas simulação' : 'Exige confirmação'}</p>
                <p className="mt-1 text-sm font-semibold opacity-80">{dryRun ? 'Nenhuma escrita será feita no GLPI.' : 'Ação real via API do GLPI.'}</p>
              </div>
            </div>
          </section>

          {closeTechnicians.length > 1 ? (
            <section className="border border-amber-300 bg-amber-50 p-4">
              <p className="eyebrow text-amber-900">Candidatos próximos</p>
              <p className="mt-1 text-sm font-semibold text-amber-900">
                A diferença entre os melhores técnicos é pequena{gap !== null ? ` (${gap.toFixed(1)} ponto(s))` : ''}. Valide antes de aprovar.
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                {closeTechnicians.map((score) => (
                  <span key={score.id} className="border border-amber-300 bg-white px-3 py-2 text-sm font-black text-amber-900">
                    {score.technician_name ?? 'Técnico'} - {Number(score.final_score).toFixed(1)}%
                  </span>
                ))}
              </div>
            </section>
          ) : null}

          <section className="grid gap-5 2xl:grid-cols-[0.95fr_1.05fr]">
            <div className="panel p-5">
              <div className="flex items-center gap-2">
                <FileText size={18} />
                <h3 className="font-black">Chamado analisado</h3>
              </div>

              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <MiniStat label="GLPI" value={`#${suggestion.glpi_ticket_id}`} />
                <MiniStat label="Categoria" value={category || '-'} />
                <MiniStat label="Confiança" value={`${Number(suggestion.confidence ?? 0).toFixed(1)}%`} />
              </div>

              <div className="mt-4">
                <p className="eyebrow">Título</p>
                <p className="mt-1 text-base font-black leading-snug text-slate-950">{title}</p>
              </div>

              <details className="mt-4 border border-slate-200 bg-slate-50" open>
                <summary className="cursor-pointer px-3 py-2 text-sm font-black text-slate-700 hover:bg-slate-100">Conteúdo usado na análise</summary>
                <pre className="max-h-80 overflow-auto whitespace-pre-wrap border-t border-slate-200 p-3 font-sans text-sm leading-6 text-slate-800">
                  {description || 'Sem descrição relevante no chamado. A decisão fica mais dependente da categoria e do histórico.'}
                </pre>
              </details>
            </div>

            <div className="panel p-5">
              <div className="flex items-center gap-2">
                <Sparkles size={18} />
                <h3 className="font-black">Por que essa recomendação?</h3>
              </div>

              <ol className="mt-4 space-y-3 text-sm leading-6">
                {audit.map((item, index) => (
                  <li key={item} className="grid grid-cols-[28px_1fr] gap-3">
                    <span className="grid size-7 place-items-center bg-[#214064] text-xs font-black text-white">{index + 1}</span>
                    <span className="border border-slate-200 bg-slate-50 px-3 py-2">{item}</span>
                  </li>
                ))}
              </ol>

              {(suggestion.warnings ?? []).length > 0 ? (
                <div className="mt-4 space-y-2">
                  {suggestion.warnings?.map((warning) => (
                    <p key={warning} className="border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-900">{warning}</p>
                  ))}
                </div>
              ) : null}
            </div>
          </section>

          <section className="space-y-4">
            <EvidenceBlock title="Ranking de técnicos" count={scores.length} defaultOpen>
              {scores.length === 0 ? (
                <p className="border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-900">Nenhum técnico entrou no ranking. Reimporte o histórico e recalcule.</p>
              ) : <TechnicianRankingTable scores={scores} />}
            </EvidenceBlock>

            <EvidenceBlock title="Chamados similares usados como referência" count={similarTickets.length}>
              <SimilarTicketsTable tickets={similarTickets} />
            </EvidenceBlock>
          </section>
        </div>

        <aside className="space-y-4 xl:sticky xl:top-24 xl:self-start">
          <section className="panel p-5">
            <h3 className="font-black">Ações</h3>
            <p className="mt-1 text-sm font-semibold text-slate-500">
              Registre uma observação antes de aprovar, rejeitar ou simular uma atribuição.
            </p>

            <textarea
              value={form.data.observation}
              onChange={(event) => form.setData('observation', event.target.value)}
              className="textarea-field mt-4 min-h-28"
              placeholder="Observação para auditoria"
            />

            {scores.length > 0 ? (
              <label htmlFor="selected-technician-id" className="mt-3 block">
                <span className="eyebrow mb-1.5 block">Técnico que você quer atribuir</span>
                <select id="selected-technician-id" className="field" defaultValue={suggestion.recommended_technician_id ?? scores[0]?.technician_id ?? ''}>
                  {scores.map((score) => (
                    <option key={score.id} value={score.technician_id}>
                      {score.technician_name ?? `ID ${score.technician_id}`} - {Number(score.final_score).toFixed(1)}%
                    </option>
                  ))}
                </select>
                <p className="mt-1 text-xs font-semibold text-slate-500">Se você escolher outro técnico e aprovar, o sistema passa a considerar essa preferência em análises parecidas.</p>
              </label>
            ) : null}

            <div className="mt-3 grid gap-2">
              <button type="button" onClick={() => postWithSelectedTechnician(`/glpi-ai/suggestions/${suggestion.id}/approve`)} className="btn btn-success w-full">
                <Check size={16} /> Aprovar sugestão
              </button>
              <button type="button" disabled={!hasTechnician} onClick={() => postWithSelectedTechnician(`/glpi-ai/suggestions/${suggestion.id}/assign-technician`)} className="btn btn-primary w-full">
                <Send size={16} /> {dryRun ? 'Simular técnico' : 'Atribuir técnico'}
              </button>
              <button type="button" disabled={!hasGroup} onClick={() => post(`/glpi-ai/suggestions/${suggestion.id}/assign-group`)} className="btn btn-secondary w-full">
                <Send size={16} /> {dryRun ? 'Simular grupo' : 'Atribuir grupo'}
              </button>
              <button type="button" onClick={() => post(`/glpi-ai/suggestions/${suggestion.id}/manual-triage`)} className="btn btn-warning w-full">
                Enviar para triagem manual
              </button>
              <button type="button" onClick={() => post(`/glpi-ai/suggestions/${suggestion.id}/reject`)} className="btn btn-danger w-full">
                <X size={16} /> Rejeitar
              </button>
              <button type="button" onClick={() => router.post(`/glpi-ai/suggestions/${suggestion.id}/recalculate`)} className="btn btn-secondary w-full">
                <RotateCcw size={16} /> Recalcular
              </button>
              <button type="button" onClick={() => router.post(`/glpi-ai/suggestions/${suggestion.id}/revalidate-ai`)} className="btn btn-secondary w-full">
                <Sparkles size={16} /> Reanalisar IA
              </button>
            </div>

            {suggestion.ai_validation_status ? (
              <div className="mt-4 border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-600">
                <p className="font-black uppercase tracking-wide text-slate-500">ValidaÃ§Ã£o da IA</p>
                <p className="mt-1">Status: {suggestion.ai_validation_status}</p>
                {suggestion.ai_validation_attempts ? <p>Tentativas: {suggestion.ai_validation_attempts}</p> : null}
                {suggestion.ai_validation_error ? <p className="mt-1 text-[#9f2f2f]">{suggestion.ai_validation_error}</p> : null}
              </div>
            ) : null}
          </section>

          <section className="panel p-5">
            <div className="flex items-center gap-2">
              <History size={18} />
              <h3 className="font-black">Histórico local</h3>
            </div>

            {(suggestion.feedbacks ?? []).length === 0 ? (
              <p className="mt-3 text-sm font-semibold text-slate-500">Nenhuma ação humana registrada ainda.</p>
            ) : (
              <div className="mt-4 space-y-3">
                {suggestion.feedbacks?.map((feedback) => (
                  <div key={feedback.id} className="border border-slate-200 bg-slate-50 p-3 text-sm">
                    <p className="font-black">{feedback.action}</p>
                    <p className="text-slate-600">{feedback.observation ?? 'Sem observação.'}</p>
                  </div>
                ))}
              </div>
            )}
          </section>
        </aside>
      </section>
    </GlpiAiLayout>
  );
}
