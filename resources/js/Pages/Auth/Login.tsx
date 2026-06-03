import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, Bot, LogIn, ShieldCheck } from 'lucide-react';

export default function Login() {
  const form = useForm({
    login: '',
    password: '',
  });
  const mainError = form.errors.login || form.errors.password;

  return (
    <main className="grid min-h-screen bg-[#f8fafc] lg:grid-cols-[0.95fr_1.05fr]">
      <Head title="Entrar" />

      <section className="hidden border-r border-slate-200 bg-[#214064] p-10 text-white lg:flex lg:flex-col lg:justify-between">
        <div>
          <div className="flex items-center gap-3">
            <div className="grid size-12 place-items-center bg-white/10 ring-1 ring-white/20">
              <Bot size={24} />
            </div>
            <div>
              <p className="text-sm font-black uppercase tracking-[0.18em] text-white/70">GLPI AI</p>
              <h1 className="text-2xl font-black">Triagem inteligente DTI</h1>
            </div>
          </div>

          <div className="mt-16 max-w-lg">
            <p className="text-4xl font-black leading-tight">Painel restrito para analise e auditoria de chamados.</p>
            <p className="mt-5 text-base leading-7 text-white/70">
              Acesso via credenciais corporativas. Apenas usuarios com description contendo DTI no LDAP podem entrar.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 text-sm font-semibold text-white/70">
          <ShieldCheck size={18} />
          Sem resposta automatica ao usuario final. Dry-run por padrao.
        </div>
      </section>

      <section className="grid place-items-center px-4 py-10">
        <form
          className="panel w-full max-w-md p-7"
          onSubmit={(event) => {
            event.preventDefault();
            form.post('/login');
          }}
        >
          <div>
            <p className="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Autenticacao</p>
            <h2 className="mt-1 text-2xl font-black text-slate-950">Entrar no painel</h2>
            <p className="mt-2 text-sm text-slate-500">Use seu usuario de rede. O acesso e liberado apenas para DTI.</p>
          </div>

          {mainError ? (
            <div className="mt-5 flex gap-3 border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800">
              <AlertTriangle className="mt-0.5 shrink-0" size={18} />
              <span>{mainError}</span>
            </div>
          ) : null}

          <div className="mt-6 space-y-4">
            <label className="block">
              <span className="mb-1.5 block text-sm font-bold text-slate-700">Usuario ou e-mail</span>
              <input
                value={form.data.login}
                onChange={(event) => form.setData('login', event.target.value)}
                className="field"
                autoComplete="username"
              />
              {form.errors.login ? <span className="mt-1 block text-sm font-semibold text-red-700">{form.errors.login}</span> : null}
            </label>

            <label className="block">
              <span className="mb-1.5 block text-sm font-bold text-slate-700">Senha</span>
              <input
                type="password"
                value={form.data.password}
                onChange={(event) => form.setData('password', event.target.value)}
                className="field"
                autoComplete="current-password"
              />
              {form.errors.password ? <span className="mt-1 block text-sm font-semibold text-red-700">{form.errors.password}</span> : null}
            </label>
          </div>

          <button type="submit" disabled={form.processing} className="btn btn-primary mt-6 w-full">
            <LogIn size={17} />
            Entrar
          </button>
        </form>
      </section>
    </main>
  );
}
