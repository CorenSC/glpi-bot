import { Head } from '@inertiajs/react';
import { Lock, Settings } from 'lucide-react';
import { GlpiAiLayout } from '../../../Layouts/GlpiAiLayout';

function stringify(value: unknown) {
  if (typeof value === 'boolean') return value ? 'Sim' : 'Não';
  if (Array.isArray(value)) return value.join(', ');
  if (typeof value === 'object' && value !== null) return JSON.stringify(value);
  return String(value ?? '-');
}

export default function SettingsIndex({ settings, editable, dryRun, autoAssign }: { settings: Record<string, unknown>; editable: boolean; dryRun: boolean; autoAssign: boolean }) {
  const rows = Object.entries(settings).filter(([key]) => !key.toLowerCase().includes('token') && !key.toLowerCase().includes('key'));

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
