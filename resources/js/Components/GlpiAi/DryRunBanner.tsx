import { ShieldCheck } from 'lucide-react';

export function DryRunBanner({ dryRun }: { dryRun: boolean }) {
  return (
    <div className={`inline-flex items-center gap-2 border px-3 py-2 text-sm font-black ${dryRun ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-900'}`}>
      <ShieldCheck size={16} />
      {dryRun ? 'Dry-run: sem escrita no GLPI' : 'Dry-run desligado'}
    </div>
  );
}
