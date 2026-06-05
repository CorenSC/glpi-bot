import { Head, useForm } from '@inertiajs/react';
import { Lock, Save, Settings } from 'lucide-react';
import type { FormEvent } from 'react';
import { GlpiAiLayout } from '../../../Layouts/GlpiAiLayout';

interface SettingDefinition {
  label: string;
  description: string;
  type: 'boolean' | 'integer' | 'float' | 'integer_list' | 'string';
  min?: number;
  max?: number;
}

function stringify(value: unknown) {
  if (typeof value === 'boolean') return value ? 'Sim' : 'Não';
  if (Array.isArray(value)) return value.join(', ');
  if (typeof value === 'object' && value !== null) return JSON.stringify(value);
  return String(value ?? '-');
}

function formValue(value: unknown) {
  if (typeof value === 'boolean') return value ? 'true' : 'false';
  if (Array.isArray(value)) return value.join(',');
  return String(value ?? '');
}

function Field({ name, definition, value, onChange, error }: {
  name: string;
  definition: SettingDefinition;
  value: string;
  onChange: (value: string) => void;
  error?: string;
}) {
  return (
    <label className="block border border-slate-200 bg-white p-4">
      <span className="text-sm font-black text-slate-950">{definition.label}</span>
      <span className="mt-1 block text-xs font-semibold leading-5 text-slate-500">{definition.description}</span>

      {definition.type === 'boolean' ? (
        <select className="field mt-3" value={value} onChange={(event) => onChange(event.target.value)}>
          <option value="true">Sim</option>
          <option value="false">Não</option>
        </select>
      ) : (
        <input
          className="field mt-3"
          type={definition.type === 'integer' || definition.type === 'float' ? 'number' : 'text'}
          step={definition.type === 'float' ? '0.01' : '1'}
          min={definition.min}
          max={definition.max}
          value={value}
          onChange={(event) => onChange(event.target.value)}
        />
      )}

      {definition.type === 'integer_list' ? (
        <span className="mt-1 block text-xs font-semibold text-slate-500">Use vírgula para separar os IDs.</span>
      ) : null}
      {error ? <span className="mt-2 block text-sm font-semibold text-red-700">{error}</span> : null}
    </label>
  );
}

export default function SettingsIndex({
  settings,
  editableSettings,
  definitions,
  editable,
  dryRun,
  autoAssign,
}: {
  settings: Record<string, unknown>;
  editableSettings: Record<string, unknown>;
  definitions: Record<string, SettingDefinition>;
  editable: boolean;
  dryRun: boolean;
  autoAssign: boolean;
}) {
  const rows = Object.entries(settings).filter(([key]) => !key.toLowerCase().includes('token') && !key.toLowerCase().includes('key'));
  const initialValues = Object.fromEntries(Object.entries(editableSettings ?? {}).map(([key, value]) => [key, formValue(value)]));
  const form = useForm<Record<string, string>>(initialValues);

  function submit(event: FormEvent) {
    event.preventDefault();
    form.post('/glpi-ai/settings', { preserveScroll: true });
  }

  return (
    <GlpiAiLayout title="Configurações" dryRun={dryRun} autoAssign={autoAssign}>
      <Head title="Configurações | GLPI BOT" />

      <section className="mb-4 border border-[#214064]/20 bg-[var(--glpi-dark)] p-4 text-white shadow-sm">
        <div className="flex items-center gap-3">
          <Settings size={20} />
          <div>
            <h2 className="font-black">Configuração operacional carregada</h2>
            <p className="mt-1 text-sm text-white/60">Esta tela é para conferência. Tokens e segredos não são exibidos.</p>
          </div>
          <span className="ml-auto border border-white/20 bg-white/10 px-2 py-1 text-xs font-black uppercase">
            {editable ? 'edição habilitada' : 'somente leitura'}
          </span>
        </div>
      </section>

      {editable ? (
        <form onSubmit={submit} className="panel mb-4">
          <div className="panel-header flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 className="section-title">Ajustes seguros pelo painel</h3>
              <p className="mt-1 text-sm font-semibold text-slate-500">
                As alterações são salvas na base interna e registradas na auditoria operacional. Tokens continuam fora do painel.
              </p>
            </div>
            <button type="submit" disabled={form.processing} className="btn btn-primary">
              <Save size={16} />
              Salvar configurações
            </button>
          </div>
          <div className="grid gap-3 p-4 lg:grid-cols-2 2xl:grid-cols-3">
            {Object.entries(definitions).map(([key, definition]) => (
              <Field
                key={key}
                name={key}
                definition={definition}
                value={form.data[key] ?? ''}
                onChange={(value) => form.setData(key, value)}
                error={form.errors[key]}
              />
            ))}
          </div>
        </form>
      ) : (
        <section className="mb-4 border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
          Edição pelo painel desativada. Para liberar, configure <code className="font-mono">GLPI_AI_ENABLE_DASHBOARD_SETTINGS_EDIT=true</code> no ambiente e limpe o cache.
        </section>
      )}

      <section className="panel">
        <div className="panel-header flex items-center gap-2">
          <Lock size={16} />
          <h3 className="section-title">Parâmetros visíveis</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="table-head">
              <tr>
                <th className="p-3">Chave</th>
                <th className="p-3">Valor</th>
              </tr>
            </thead>
            <tbody>
              {rows.map(([key, value]) => (
                <tr key={key} className="table-row">
                  <td className="p-3 font-mono text-xs font-black">{key}</td>
                  <td className="max-w-4xl p-3 font-mono text-xs">{stringify(value)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </GlpiAiLayout>
  );
}
