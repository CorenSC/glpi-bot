export type RiskLevel = 'low' | 'medium' | 'high';
export type SuggestionStatus = 'pending' | 'accepted' | 'rejected' | 'auto_assigned' | 'manual_triage' | 'failed' | 'ignored' | 'glpi_closed';

export interface Suggestion {
  id: number;
  glpi_ticket_id: number;
  title?: string;
  category_name?: string;
  recommended_action: string;
  recommended_technician_id?: number;
  recommended_technician_name?: string;
  recommended_group_id?: number;
  recommended_group_name?: string;
  confidence: number;
  reason?: string;
  warnings?: string[];
  risk_level: RiskLevel;
  status: SuggestionStatus;
  archived_at?: string;
  ai_validation_status?: 'pending' | 'running' | 'completed' | 'failed';
  ai_validation_attempts?: number;
  ai_validation_next_retry_at?: string;
  ai_validation_error?: string;
  created_at: string;
  analysis_run?: AnalysisRun;
  feedbacks?: HumanFeedback[];
}

export interface AnalysisRun {
  id: number;
  status: string;
  canonical_text?: string;
  deterministic_decision?: Record<string, unknown>;
  ai_decision?: Record<string, unknown>;
  final_decision?: Record<string, unknown>;
  similar_tickets?: SimilarTicket[];
  technician_scores?: TechnicianScore[];
  error_message?: string;
  duration_ms?: number;
}

export interface HumanFeedback {
  id: number;
  action: string;
  observation?: string;
  previous_status?: string;
  new_status?: string;
  created_at?: string;
}

export interface SimilarTicket {
  id: number;
  glpi_ticket_id: number;
  title?: string;
  similarity_score: number;
  final_similarity_score: number;
  assigned_technician_name?: string;
  solver_technician_name?: string;
  assigned_group_name?: string;
}

export interface TechnicianScore {
  id: number;
  rank_position: number;
  technician_name?: string;
  group_name?: string;
  final_score: number;
  text_similarity_score: number;
  category_score: number;
  context_score?: number;
  history_score: number;
  recency_score: number;
  workload_score: number;
  is_blocked: boolean;
  blocked_reason?: string;
  metadata?: {
    human_feedback_score?: number;
    human_feedback_positive?: number;
    human_feedback_negative?: number;
    human_feedback_total?: number;
    human_feedback_adjustment?: number;
    context_score?: number;
    context_adjustment?: number;
    evidence_share?: number;
    top_evidence_count?: number;
    top_evidence_share?: number;
    weighted_evidence_score?: number;
    dominance_adjustment?: number;
    dominant_evidence_rule_applied?: boolean;
    [key: string]: unknown;
  };
}
