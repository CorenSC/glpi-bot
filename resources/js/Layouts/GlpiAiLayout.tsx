import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, BarChart3, ClipboardCheck, FileSearch, LogOut, Settings, ShieldCheck } from 'lucide-react';
import { useEffect } from 'react';
import type { PropsWithChildren } from 'react';
import { Toaster, toast } from 'sonner';
import { DryRunBanner } from '../Components/GlpiAi/DryRunBanner';

interface Props extends PropsWithChildren {
  dryRun?: boolean;
  autoAssign?: boolean;
  title: string;
}

const nav = [
  ['Dashboard', '/glpi-ai', BarChart3],
  ['Sugestões', '/glpi-ai/suggestions', ClipboardCheck],
  ['Análise manual', '/glpi-ai/manual-analysis', FileSearch],
  ['Auditoria', '/glpi-ai/audit', ShieldCheck],
  ['Configurações', '/glpi-ai/settings', Settings],
] as const;

export function GlpiAiLayout({ children, dryRun = true, autoAssign = false, title }: Props) {
  const { url, props } = usePage<{
    flash?: {
      success?: string | null;
      error?: string | null;
    };
  }>();

  useEffect(() => {
    if (props.flash?.success) {
      toast.success(props.flash.success);
    }

    if (props.flash?.error) {
      toast.error(props.flash.error);
    }
  }, [props.flash?.success, props.flash?.error]);

  return (
    <main className="min-h-screen bg-slate-50 text-slate-950">
      <Toaster richColors position="top-right" closeButton />

      <aside className="fixed inset-y-0 left-0 hidden w-72 border-r border-slate-200 bg-white lg:flex lg:flex-col">
        <div className="border-b border-slate-200 px-5 py-5">
          <Link href="/glpi-ai" className="flex items-center gap-3">
            <img src="/images/coren-colorido.png" alt="Coren" className="h-12 w-auto object-contain" />
            <div className="border-l border-slate-200 pl-3">
              <p className="text-base font-black tracking-wide text-slate-950">GLPI BOT</p>
              <p className="text-xs font-semibold text-slate-500">triagem técnica</p>
            </div>
          </Link>
        </div>

        <nav className="flex-1 space-y-1 px-3 py-4">
          {nav.map(([label, href, Icon]) => {
            const active = url === href || (href !== '/glpi-ai' && url.startsWith(href));

            return (
              <Link
                key={href}
                href={href}
                className={`flex items-center gap-3 px-3 py-2.5 text-sm font-bold outline-none focus-visible:ring-2 focus-visible:ring-[#214064] focus-visible:ring-offset-2 ${
                  active
                    ? 'bg-[#214064] text-white shadow-sm'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-[#214064]'
                }`}
              >
                <Icon size={17} />
                {label}
              </Link>
            );
          })}
        </nav>

        <div className="border-t border-slate-200 p-4">
          <div className={`mb-3 rounded-lg border px-3 py-3 text-xs leading-5 ${dryRun ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-900'}`}>
            <p className="font-black uppercase tracking-[0.12em]">{dryRun ? 'Dry-run ativo' : 'Execução real'}</p>
            <p>{dryRun ? 'Nenhuma alteração será feita no GLPI.' : 'Ações podem escrever via API.'}</p>
          </div>
          <button
            type="button"
            onClick={() => router.post('/logout')}
            className="btn btn-secondary w-full"
          >
            <LogOut size={16} />
            Sair
          </button>
        </div>
      </aside>

      <section className="lg:pl-72">
        <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
          <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div className="flex items-center gap-4">
              <img src="/images/corensc-preto.png" alt="Coren-SC" className="hidden h-9 w-auto object-contain sm:block" />
              <div className="h-9 border-l border-slate-200" />
              <div>
                <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-500">GLPI BOT</p>
                <h1 className="text-2xl font-black tracking-tight text-slate-950">{title}</h1>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {autoAssign && !dryRun ? (
                <span className="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-black text-red-900">
                  <AlertTriangle size={16} />
                  Autoatribuição real
                </span>
              ) : null}
              <DryRunBanner dryRun={dryRun} />
              <button
                type="button"
                onClick={() => router.post('/logout')}
                className="btn btn-secondary hidden md:inline-flex"
              >
                <LogOut size={16} />
                Sair
              </button>
            </div>
          </div>
        </header>

        <div className="p-4 xl:p-6">{children}</div>
      </section>
    </main>
  );
}
