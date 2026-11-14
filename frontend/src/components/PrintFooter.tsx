import { useTranslation } from 'react-i18next';

export interface PrintFooterProps {
  showSignature?: boolean;
  notes?: string;
}

export function PrintFooter({ showSignature = true, notes }: PrintFooterProps) {
  const { t } = useTranslation();

  return (
    <div className="hidden print:block print:mt-6 print:pt-4 print:border-t print:border-slate-300 print:break-inside-avoid">
      {notes && (
        <div className="mb-4 text-xs italic text-slate-500">
          * {notes}
        </div>
      )}

      {showSignature && (
        <div className="flex justify-end my-4">
          <div className="w-72 rounded-lg border border-slate-400 p-3 text-center">
            <div className="text-xs font-bold uppercase text-slate-800">
              {t('print.syndicStamp', { defaultValue: 'Pour le Syndic de Copropriété' })}
            </div>
            <div className="text-[10px] text-slate-500">
              {t('print.signatureStamp', { defaultValue: 'Cachet & Signature' })}
            </div>
            <div className="my-8" />
            <div className="text-start text-[10px] text-slate-500">
              {t('print.dateLabel', { defaultValue: 'Date :' })} ________________________
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
