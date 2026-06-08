import { Head, Link, router, useForm } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowLeft,
  Check,
  Clock,
  ExternalLink,
  FileText,
  History,
  ListChecks,
  RotateCcw,
  Send,
  Sparkles,
  UserCheck,
  Users,
  X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ConfidenceBadge, RiskBadge, StatusBadge } from '../../../Components/GlpiAi/Badges';
import { SimilarTicketsTable, TechnicianRankingTable } from '../../../Components/GlpiAi/Tables';
import { GlpiAiLayout } from '../../../Layouts/GlpiAiLayout';
import type { Suggestion, TechnicianScore } from '../../../Types/glpi-ai';

const canonicalLabels = [
  'Título',
  'Titulo',
  'Categoria detectada no título',
  'Categoria detectada no titulo',
  'Categoria informada',
  'Descrição',
  'Descricao',
  'Solução',
  'Solucao',
  'Grupo atribuído',
  'Grupo atribuido',
  'Técnico atribuído',
  'Tecnico atribuido',
  'Técnico solucionador',
  'Tecnico solucionador',
  'Histórico resumido',
  'Historico resumido',
];

const feedbackReasons = [
  { value: '', label: 'Selecione um motivo' },
  { value: 'recommendation_correct', label: 'Recomendação correta' },
  { value: 'better_technician', label: 'Outro técnico conhece melhor' },
  { value: 'wrong_technician', label: 'Técnico errado' },
  { value: 'bad_similar_tickets', label: 'Chamados similares ruins' },
  { value: 'wrong_category', label: 'Categoria interpretada errada' },
  { value: 'weak_context', label: 'Contexto fraco' },
  { value: 'not_dti_ticket', label: 'Não era chamado para DTI' },
];

const feedbackReasonLabels = Object.fromEntries(feedbackReasons.map((reason) => [reason.value, reason.label])) as Record<string, string>;

const feedbackActionLabels: Record<string, string> = {
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
};

const aiValidationLabels: Record<string, string> = {
  pending: 'Pendente',
  running: 'Em execução',
  completed: 'Concluída',
  failed: 'Falhou',
};

const tabLabels = {
  summary: 'Resumo',
  evidence: 'Evidências',
  ranking: 'Ranking',
  audit: 'Auditoria',
  content: 'Conteúdo',
} as const;

type TabKey = keyof typeof tabLabels;

function actionLabel(action: string) {
  if (action === 'assign_to_technician') return 'Sugerir técnico';
  if (action === 'assign_to_group') return 'Sugerir grupo';
  return 'Triagem manual';
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

function percent(value?: number | null) {
  if (typeof value !== 'number') return '-';
  return `${Number(value).toFixed(1)}%`;
}

function scoreGap(scores: TechnicianScore[]) {
  if (scores.length < 2) return null;
  return Math.abs(Number(scores[0]?.final_score ?? 0) - Number(scores[1]?.final_score ?? 0));
}

function pendingAge(createdAt?: string) {
  if (!createdAt) return '-';
  const minutes = Math.max(0, Math.floor((Date.now() - new Date(createdAt).getTime()) / 60000));
  if (minutes < 60) return `${minutes} min`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ${minutes % 60}min`;
  return `${Math.floor(hours / 24)}d ${hours % 24}h`;
}

function slaTone(createdAt?: string) {
  if (!createdAt) return 'border-slate-200 bg-slate-50 text-slate-700';
  const minutes = Math.max(0, Math.floor((Date.now() - new Date(createdAt).getTime()) / 60000));
  if (minutes >= 240) return 'border-red-200 bg-red-50 text-red-900';
  if (minutes >= 60) return 'border-amber-300 bg-amber-50 text-amber-900';
  return 'border-slate-200 bg-slate-50 text-slate-700';
}

function MiniStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="border border-slate-200 bg-slate-50 px-3 py-2">
      <p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">{label}</p>
      <p className="mt-1 font-black text-slate-950">{value}</p>
    </div>
  );
}

function SkeletonBlock({ className = '' }: { className?: string }) {
  return <div className={`animate-pulse bg-slate-200/80 ${className}`} />;
}

function RecalculationSkeleton() {
  return (
    <div className="space-y-5" aria-busy="true" aria-live="polite">
      <section className="panel overflow-hidden">
        <div className="bg-[#214064] p-5">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="w-full max-w-3xl space-y-3">
              <SkeletonBlock className="h-3 w-40 bg-white/25" />
              <SkeletonBlock className="h-9 w-72 bg-white/35" />
              <SkeletonBlock className="h-4 w-full max-w-xl bg-white/25" />
            </div>
            <div className="flex gap-2">
              <SkeletonBlock className="h-8 w-24 bg-white/30" />
              <SkeletonBlock className="h-8 w-20 bg-white/30" />
              <SkeletonBlock className="h-8 w-20 bg-white/30" />
            </div>
          </div>
        </div>

        <div className="grid gap-3 p-4 md:grid-cols-3">
          <SkeletonBlock className="h-28" />
          <SkeletonBlock className="h-28" />
          <SkeletonBlock className="h-28" />
        </div>
      </section>

      <section className="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
        <div className="panel p-5">
          <SkeletonBlock className="h-5 w-44" />
          <div className="mt-4 space-y-3">
            <SkeletonBlock className="h-12" />
            <SkeletonBlock className="h-12" />
            <SkeletonBlock className="h-12" />
          </div>
        </div>

        <div className="panel p-5">
          <SkeletonBlock className="h-5 w-56" />
          <div className="mt-4 space-y-3">
            <SkeletonBlock className="h-12" />
            <SkeletonBlock className="h-12" />
            <SkeletonBlock className="h-12" />
          </div>
        </div>
      </section>

      <p className="text-sm font-semibold text-slate-500">
        Recalculando a recomendação. A tela será atualizada automaticamente.
      </p>
    </div>
  );
}

function buildDecisionReasons(suggestion: Suggestion, scores: TechnicianScore[]) {
  const run = suggestion.analysis_run;
  const similar = run?.similar_tickets ?? [];
  const top = scores[0];
  const second = scores[1];
  const ai = (run?.ai_decision ?? {}) as Record<string, any>;
  const reasons = [];

  if (top) {
    reasons.push(`Melhor candidato: ${top.technician_name ?? 'técnico sem nome'} com ${Number(top.final_score).toFixed(1)}%.`);
  } else {
    reasons.push('Nenhum técnico entrou no ranking final.');
  }

  if (second) {
    reasons.push(`Segundo colocado: ${second.technician_name ?? 'técnico sem nome'} com ${Number(second.final_score).toFixed(1)}%.`);
  }

  reasons.push(`Base usada: ${similar.length} chamado(s) similar(es).`);

  if (suggestion.reason) {
    reasons.push(suggestion.reason);
  }

  if (ai.reason) {
    reasons.push(`Validação da IA: ${ai.reason}`);
  }

  if (suggestion.block_reason) {
    reasons.push(`Atenção: ${suggestion.block_reason}`);
  }

  return reasons.slice(0, 5);
}

function compactTitle(value: string) {
  return value.length > 180 ? `${value.slice(0, 180)}...` : value;
}

export default function SuggestionShow({ suggestion, dryRun, autoAssign, glpiWebBaseUrl }: { suggestion: Suggestion; dryRun: boolean; autoAssign: boolean; glpiWebBaseUrl: string }) {
  const form = useForm({ observation: '', reason_code: '' });
  const [processingAction, setProcessingAction] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabKey>('summary');
  const run = suggestion.analysis_run;
  const scores = run?.technician_scores ?? [];
  const similarTickets = run?.similar_tickets ?? [];
  const hasTechnician = Boolean(suggestion.recommended_technician_name || suggestion.recommended_technician_id);
  const hasGroup = Boolean(suggestion.recommended_group_name || suggestion.recommended_group_id);
  const title = canonicalValue(run?.canonical_text, 'Título') || canonicalValue(run?.canonical_text, 'Titulo') || suggestion.title || '-';
  const category = canonicalValue(run?.canonical_text, 'Categoria detectada no título')
    || canonicalValue(run?.canonical_text, 'Categoria detectada no titulo')
    || suggestion.category_name
    || '-';
  const description = canonicalSection(run?.canonical_text, 'Descrição') || canonicalSection(run?.canonical_text, 'Descricao');
  const recommendedPerson = hasTechnician ? suggestion.recommended_technician_name ?? `ID ${suggestion.recommended_technician_id}` : 'Nenhum técnico recomendado';
  const recommendedGroup = hasGroup ? suggestion.recommended_group_name ?? `ID ${suggestion.recommended_group_id}` : 'Nenhum grupo';
  const ticketUrl = glpiTicketUrl(glpiWebBaseUrl, suggestion.glpi_ticket_id);
  const recalculationPending = suggestion.action_taken === 'recalculate_requested';
  const actionProcessing = form.processing || processingAction !== null || recalculationPending;
  const gap = scoreGap(scores);
  const closeTechnicians = scores.slice(0, 4).filter((score, index) => index === 0 || Math.abs(Number(scores[0]?.final_score ?? 0) - Number(score.final_score ?? 0)) <= 5);
  const decisionReasons = useMemo(() => buildDecisionReasons(suggestion, scores), [suggestion, scores]);
  const aiDecision = (run?.ai_decision ?? {}) as Record<string, any>;
  const finalDecision = (run?.final_decision ?? {}) as Record<string, any>;

  function post(path: string, action: string) {
    setProcessingAction(action);
    form.post(path, {
      preserveScroll: true,
      onFinish: () => setProcessingAction(null),
    });
  }

  function postWithSelectedTechnician(path: string, action: string) {
    const selected = document.querySelector<HTMLSelectElement>('#selected-technician-id')?.value;
    setProcessingAction(action);
    router.post(path, {
      observation: form.data.observation,
      reason_code: form.data.reason_code,
      technician_id: selected || suggestion.recommended_technician_id,
    }, {
      preserveScroll: true,
      onFinish: () => setProcessingAction(null),
    });
  }

  function queueAction(path: string, action: string) {
    setProcessingAction(action);
    router.post(path, {}, {
      preserveScroll: true,
      onFinish: () => setProcessingAction(null),
    });
  }

  useEffect(() => {
    if (!recalculationPending) return;

    const interval = window.setInterval(() => {
      router.reload({ only: ['suggestion'], preserveScroll: true });
    }, 5000);

    return () => window.clearInterval(interval);
  }, [recalculationPending]);

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
          {recalculationPending ? (
            <RecalculationSkeleton />
          ) : (
            <>
              <section className="panel overflow-hidden">
                <div className="border-b border-slate-200 bg-[#214064] p-5 text-white">
                  <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="max-w-4xl">
                      <p className="text-[11px] font-black uppercase tracking-[0.18em] text-white/60">Decisão recomendada</p>
                      <h2 className="mt-2 text-3xl font-black tracking-tight">{actionLabel(suggestion.recommended_action)}</h2>
                      <p className="mt-2 text-sm leading-6 text-white/80">{suggestion.reason || 'Sem justificativa registrada.'}</p>
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

                  <div className={`border p-4 ${dryRun ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-900'}`}>
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

              <section className="panel overflow-hidden">
                <div className="border-b border-slate-200 bg-white px-3 pt-3">
                  <div className="flex gap-1 overflow-x-auto" role="tablist" aria-label="Detalhes da sugestão">
                    {(Object.keys(tabLabels) as TabKey[]).map((tab) => (
                      <button
                        key={tab}
                        type="button"
                        onClick={() => setActiveTab(tab)}
                        className={`min-h-11 whitespace-nowrap border px-4 text-sm font-black transition ${
                          activeTab === tab
                            ? 'border-[#214064] bg-[#214064] text-white'
                            : 'border-transparent bg-white text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-950'
                        }`}
                        role="tab"
                        aria-selected={activeTab === tab}
                      >
                        {tabLabels[tab]}
                      </button>
                    ))}
                  </div>
                </div>

                <div className="p-5">
                  {activeTab === 'summary' ? (
                    <div className="grid gap-5 2xl:grid-cols-[0.9fr_1.1fr]">
                      <div>
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
                          <p className="mt-1 text-base font-black leading-snug text-slate-950">{compactTitle(title)}</p>
                        </div>

                        <div className={`mt-4 border px-3 py-2 ${slaTone(suggestion.created_at)}`}>
                          <div className="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.14em] opacity-70">
                            <Clock size={13} />
                            SLA local
                          </div>
                          <p className="mt-1 font-black">{suggestion.status === 'pending' ? pendingAge(suggestion.created_at) : 'Finalizado'}</p>
                        </div>
                      </div>

                      <div>
                        <div className="flex items-center gap-2">
                          <Sparkles size={18} />
                          <h3 className="font-black">Por que essa recomendação?</h3>
                        </div>

                        <ol className="mt-4 space-y-3 text-sm leading-6">
                          {decisionReasons.map((item, index) => (
                            <li key={`${index}-${item}`} className="grid grid-cols-[28px_1fr] gap-3">
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
                    </div>
                  ) : null}

                  {activeTab === 'evidence' ? (
                    <div>
                      <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                          <h3 className="font-black">Chamados similares usados como referência</h3>
                          <p className="text-sm font-semibold text-slate-500">{similarTickets.length} registro(s). Use isso para conferir se a base realmente parece parecida.</p>
                        </div>
                      </div>
                      <SimilarTicketsTable tickets={similarTickets} />
                    </div>
                  ) : null}

                  {activeTab === 'ranking' ? (
                    <div>
                      <div className="mb-4">
                        <h3 className="font-black">Ranking de técnicos</h3>
                        <p className="text-sm font-semibold text-slate-500">
                          Esta aba mostra o cálculo completo. A tela inicial esconde esses detalhes para não atrapalhar a decisão.
                        </p>
                      </div>
                      {scores.length === 0 ? (
                        <p className="border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-900">Nenhum técnico entrou no ranking. Reimporte o histórico e recalcule.</p>
                      ) : (
                        <TechnicianRankingTable scores={scores} />
                      )}
                    </div>
                  ) : null}

                  {activeTab === 'audit' ? (
                    <div className="grid gap-5 2xl:grid-cols-2">
                      <section>
                        <div className="flex items-center gap-2">
                          <ListChecks size={18} />
                          <h3 className="font-black">Validação e decisão</h3>
                        </div>

                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                          <MiniStat label="Ranking" value={percent(suggestion.ranking_confidence ?? suggestion.confidence)} />
                          <MiniStat label="IA" value={percent(suggestion.ai_confidence)} />
                          <MiniStat label="Final" value={percent(suggestion.final_confidence ?? suggestion.confidence)} />
                        </div>

                        <div className="mt-4 space-y-3 text-sm leading-6">
                          <p className="border border-slate-200 bg-slate-50 p-3">
                            Status da IA: <strong>{suggestion.ai_validation_status ? aiValidationLabels[suggestion.ai_validation_status] ?? suggestion.ai_validation_status : 'Não registrado'}</strong>
                            {suggestion.ai_validation_attempts ? ` · tentativas: ${suggestion.ai_validation_attempts}` : ''}
                          </p>
                          {aiDecision.reason ? <p className="border border-slate-200 bg-slate-50 p-3">Validação da IA: {aiDecision.reason}</p> : null}
                          {finalDecision.recommended_action ? <p className="border border-slate-200 bg-slate-50 p-3">Decisão final: {actionLabel(String(finalDecision.recommended_action))}</p> : null}
                          {suggestion.ai_validation_error ? <p className="border border-red-200 bg-red-50 p-3 font-semibold text-red-900">{suggestion.ai_validation_error}</p> : null}
                        </div>
                      </section>

                      <section>
                        <div className="flex items-center gap-2">
                          <History size={18} />
                          <h3 className="font-black">Histórico local</h3>
                        </div>

                        {(suggestion.feedbacks ?? []).length === 0 ? (
                          <p className="mt-4 border border-slate-200 bg-slate-50 p-3 text-sm font-semibold text-slate-500">Nenhuma ação humana registrada ainda.</p>
                        ) : (
                          <div className="mt-4 space-y-3">
                            {suggestion.feedbacks?.map((feedback) => (
                              <div key={feedback.id} className="border border-slate-200 bg-slate-50 p-3 text-sm">
                                <p className="font-black">{feedbackActionLabels[feedback.action] ?? feedback.action}</p>
                                <p className="mt-1 text-xs font-bold uppercase tracking-wide text-slate-500">
                                  Motivo: {feedback.reason_code ? feedbackReasonLabels[feedback.reason_code] ?? feedback.reason_code : 'não informado'} · peso: {typeof feedback.learning_weight === 'number' ? feedback.learning_weight.toFixed(2) : '-'}
                                </p>
                                <p className="mt-1 text-slate-600">{feedback.observation ?? 'Sem observação.'}</p>
                              </div>
                            ))}
                          </div>
                        )}
                      </section>
                    </div>
                  ) : null}

                  {activeTab === 'content' ? (
                    <div>
                      <h3 className="font-black">Conteúdo usado na análise</h3>
                      <p className="mt-1 text-sm font-semibold text-slate-500">Texto normalizado enviado para cálculo de similaridade e validação.</p>
                      <pre className="mt-4 max-h-[560px] overflow-auto whitespace-pre-wrap border border-slate-200 bg-slate-50 p-4 font-sans text-sm leading-6 text-slate-800">
                        {description || 'Sem descrição relevante no chamado. A decisão fica mais dependente da categoria e do histórico.'}
                      </pre>
                    </div>
                  ) : null}
                </div>
              </section>
            </>
          )}
        </div>

        <aside className="space-y-4 xl:sticky xl:top-24 xl:self-start">
          <section className="panel p-5">
            <h3 className="font-black">Ações</h3>
            <p className="mt-1 text-sm font-semibold text-slate-500">
              Registre uma observação antes de aprovar, rejeitar ou simular uma atribuição.
            </p>

            <label htmlFor="feedback-reason-code" className="mt-4 block">
              <span className="eyebrow mb-1.5 block">Motivo da validação</span>
              <select
                id="feedback-reason-code"
                className="field"
                value={form.data.reason_code}
                onChange={(event) => form.setData('reason_code', event.target.value)}
              >
                {feedbackReasons.map((reason) => (
                  <option key={reason.value} value={reason.value}>{reason.label}</option>
                ))}
              </select>
              <p className="mt-1 text-xs font-semibold text-slate-500">
                Esse motivo entra no aprendizado auditável do GLPI BOT.
              </p>
            </label>

            <textarea
              value={form.data.observation}
              onChange={(event) => form.setData('observation', event.target.value)}
              className="textarea-field mt-3 min-h-28"
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
              <button type="button" disabled={actionProcessing} onClick={() => postWithSelectedTechnician(`/glpi-ai/suggestions/${suggestion.id}/approve`, 'approve')} className="btn btn-success w-full">
                <Check size={16} /> {processingAction === 'approve' ? 'Aprovando...' : 'Aprovar sugestão'}
              </button>
              <button type="button" disabled={!hasTechnician || actionProcessing} onClick={() => postWithSelectedTechnician(`/glpi-ai/suggestions/${suggestion.id}/assign-technician`, 'assign-technician')} className="btn btn-primary w-full">
                <Send size={16} /> {processingAction === 'assign-technician' ? 'Enviando...' : (dryRun ? 'Simular técnico' : 'Atribuir técnico')}
              </button>
              <button type="button" disabled={!hasGroup || actionProcessing} onClick={() => post(`/glpi-ai/suggestions/${suggestion.id}/assign-group`, 'assign-group')} className="btn btn-secondary w-full">
                <Send size={16} /> {processingAction === 'assign-group' ? 'Enviando...' : (dryRun ? 'Simular grupo' : 'Atribuir grupo')}
              </button>
              <button type="button" disabled={actionProcessing} onClick={() => post(`/glpi-ai/suggestions/${suggestion.id}/manual-triage`, 'manual-triage')} className="btn btn-warning w-full">
                {processingAction === 'manual-triage' ? 'Enviando...' : 'Enviar para triagem manual'}
              </button>
              <button type="button" disabled={actionProcessing} onClick={() => post(`/glpi-ai/suggestions/${suggestion.id}/reject`, 'reject')} className="btn btn-danger w-full">
                <X size={16} /> {processingAction === 'reject' ? 'Rejeitando...' : 'Rejeitar'}
              </button>
              <button type="button" disabled={actionProcessing} onClick={() => queueAction(`/glpi-ai/suggestions/${suggestion.id}/recalculate`, 'recalculate')} className="btn btn-secondary w-full">
                <RotateCcw className={processingAction === 'recalculate' || recalculationPending ? 'animate-spin' : ''} size={16} />
                {recalculationPending ? 'Recalculando...' : 'Recalcular'}
              </button>
              <button type="button" disabled={actionProcessing} onClick={() => queueAction(`/glpi-ai/suggestions/${suggestion.id}/revalidate-ai`, 'revalidate-ai')} className="btn btn-secondary w-full">
                <Sparkles size={16} /> {processingAction === 'revalidate-ai' ? 'Enviando...' : 'Reanalisar IA'}
              </button>
            </div>
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
                {suggestion.feedbacks?.slice(0, 3).map((feedback) => (
                  <div key={feedback.id} className="border border-slate-200 bg-slate-50 p-3 text-sm">
                    <p className="font-black">{feedbackActionLabels[feedback.action] ?? feedback.action}</p>
                    <p className="mt-1 text-xs font-bold uppercase tracking-wide text-slate-500">
                      Motivo: {feedback.reason_code ? feedbackReasonLabels[feedback.reason_code] ?? feedback.reason_code : 'não informado'}
                    </p>
                  </div>
                ))}
                {(suggestion.feedbacks?.length ?? 0) > 3 ? (
                  <button type="button" onClick={() => setActiveTab('audit')} className="link-action text-sm">Ver histórico completo</button>
                ) : null}
              </div>
            )}
          </section>
        </aside>
      </section>
    </GlpiAiLayout>
  );
}
