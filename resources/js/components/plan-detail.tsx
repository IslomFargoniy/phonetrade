import { Chip } from '@/components/chip';
import type { ChipTone } from '@/components/chip';
import { DataTable, Td, Th } from '@/components/data-table';
import { StatList } from '@/components/stat-list';
import { t } from '@/lib/i18n';
import type { PlanRow, PlanState } from '@/types';

export const planTone: Record<PlanState, ChipTone> = {
    late: 'danger',
    today: 'warning',
    upcoming: 'neutral',
    active: 'info',
    closed: 'success',
    cancelled: 'neutral',
};

const rowLabel = {
    paid: 'plan.row.paid',
    late: 'plan.row.late',
    partial: 'plan.row.partial',
    pending: 'plan.row.pending',
} as const;

const rowClass = {
    paid: 'font-semibold text-success',
    late: 'font-bold text-danger',
    partial: 'font-semibold text-warning',
    pending: 'text-muted-foreground',
} as const;

/**
 * Shartnomaning qisqa ko'rinishi: to'langan, qolgan va to'lov jadvali.
 */
export function PlanDetail({ plan }: { plan: PlanRow }) {
    return (
        <>
            <StatList
                items={[
                    { label: t('plan.paid'), value: plan.paid_label },
                    {
                        label: t('plan.remaining'),
                        value: plan.remaining_label,
                        strong: true,
                    },
                    { label: t('plan.total'), value: plan.total_label },
                ]}
            />

            <DataTable minWidth={320}>
                <thead>
                    <tr>
                        <Th>№</Th>
                        <Th>{t('sales.col.date')}</Th>
                        <Th right>{t('pos.amount')}</Th>
                        <Th>{t('sales.col.status')}</Th>
                    </tr>
                </thead>
                <tbody>
                    {plan.schedule.map((row) => (
                        <tr key={row.no}>
                            <Td className="py-2 text-muted-foreground">
                                {row.no}
                            </Td>
                            <Td className="py-2 num">{row.date}</Td>
                            <Td right className="py-2 num">
                                {row.amount_label}
                                {row.state === 'partial' && (
                                    <span className="block text-xs text-muted-foreground">
                                        {t('plan.row.paid_part', {
                                            amount: row.paid_label,
                                        })}
                                    </span>
                                )}
                            </Td>
                            <Td className={`py-2 ${rowClass[row.state]}`}>
                                {t(rowLabel[row.state])}
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </DataTable>
        </>
    );
}

export function PlanStateChip({ plan }: { plan: PlanRow }) {
    return <Chip tone={planTone[plan.state]}>{plan.state_label}</Chip>;
}
