import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronLeft, ChevronRight, ChevronUp, ChevronsUpDown, Search } from 'lucide-react';
import { Input } from './Input';

export interface DataTableColumn<T> {
  key: string;
  label: string;
  sortable?: boolean;
  align?: 'start' | 'end' | 'center';
  sortValue?: (row: T) => string | number | null;
  render?: (row: T) => ReactNode;
  className?: string;
}

interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  data: T[];
  rowKey: (row: T) => string | number;
  searchPlaceholder?: string;
  searchableText?: (row: T) => string;
  actions?: (row: T) => ReactNode;
  emptyState?: ReactNode;
  isLoading?: boolean;
  loadingText?: string;
  pageSize?: number;
  pageSizeOptions?: (number | 'all')[];
  pageInfoLabel?: (from: number, to: number, total: number) => string;
  previousLabel?: string;
  nextLabel?: string;
  rowsPerPageLabel?: string;
  allRowsLabel?: string;
}

type SortDirection = 'asc' | 'desc' | null;

const alignClasses: Record<'start' | 'end' | 'center', string> = {
  start: 'text-start',
  end: 'text-end',
  center: 'text-center',
};

function defaultSortValue<T>(row: T, key: string): string | number | null {
  const value = (row as Record<string, unknown>)[key];
  if (value == null) {
    return null;
  }
  if (typeof value === 'number' || typeof value === 'string') {
    return value;
  }
  return String(value);
}

function defaultRender<T>(row: T, key: string): ReactNode {
  const value = (row as Record<string, unknown>)[key];
  return value == null ? '—' : String(value);
}

export function DataTable<T>({
  columns,
  data,
  rowKey,
  searchPlaceholder,
  searchableText,
  actions,
  emptyState,
  isLoading = false,
  loadingText,
  pageSize = 10,
  pageSizeOptions = [10, 25, 50, 'all'],
  pageInfoLabel,
  previousLabel,
  nextLabel,
  rowsPerPageLabel,
  allRowsLabel,
}: DataTableProps<T>) {
  const [query, setQuery] = useState('');
  const [sortKey, setSortKey] = useState<string | null>(null);
  const [sortDirection, setSortDirection] = useState<SortDirection>(null);
  const [page, setPage] = useState(1);
  const [rowsPerPage, setRowsPerPage] = useState<number | 'all'>(pageSize);

  const filtered = useMemo(() => {
    if (!searchableText || !query.trim()) {
      return data;
    }
    const needle = query.trim().toLowerCase();
    return data.filter((row) => searchableText(row).toLowerCase().includes(needle));
  }, [data, query, searchableText]);

  useEffect(() => {
    setPage(1);
  }, [query, sortKey, sortDirection, data, rowsPerPage]);

  const sorted = useMemo(() => {
    if (!sortKey || !sortDirection) {
      return filtered;
    }
    const column = columns.find((c) => c.key === sortKey);
    if (!column) {
      return filtered;
    }
    const getValue = column.sortValue ?? ((row: T) => defaultSortValue(row, column.key));
    const factor = sortDirection === 'asc' ? 1 : -1;

    return [...filtered].sort((a, b) => {
      const va = getValue(a);
      const vb = getValue(b);
      if (va == null && vb == null) return 0;
      if (va == null) return 1;
      if (vb == null) return -1;
      if (typeof va === 'number' && typeof vb === 'number') {
        return (va - vb) * factor;
      }
      return String(va).localeCompare(String(vb)) * factor;
    });
  }, [filtered, sortKey, sortDirection, columns]);

  const effectivePageSize = rowsPerPage === 'all' ? Math.max(1, sorted.length) : rowsPerPage;
  const totalPages = rowsPerPage === 'all' ? 1 : Math.max(1, Math.ceil(sorted.length / effectivePageSize));
  const currentPage = Math.min(page, totalPages);
  const paginated = useMemo(
    () =>
      rowsPerPage === 'all' ? sorted : sorted.slice((currentPage - 1) * effectivePageSize, currentPage * effectivePageSize),
    [sorted, currentPage, effectivePageSize, rowsPerPage],
  );
  const rangeFrom = sorted.length === 0 ? 0 : (currentPage - 1) * effectivePageSize + 1;
  const rangeTo = rowsPerPage === 'all' ? sorted.length : Math.min(currentPage * effectivePageSize, sorted.length);

  function toggleSort(key: string) {
    if (sortKey !== key) {
      setSortKey(key);
      setSortDirection('asc');
      return;
    }
    if (sortDirection === 'asc') {
      setSortDirection('desc');
      return;
    }
    if (sortDirection === 'desc') {
      setSortKey(null);
      setSortDirection(null);
      return;
    }
    setSortDirection('asc');
  }

  return (
    <div className="flex flex-col gap-3">
      {(searchableText || pageSizeOptions.length > 0) && (
        <div className="flex flex-wrap items-center justify-between gap-3">
          {searchableText ? (
            <div className="relative max-w-sm grow">
              <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
              <Input
                type="text"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={searchPlaceholder}
                className="ps-9"
              />
            </div>
          ) : (
            <span />
          )}

          {pageSizeOptions.length > 0 && (
            <label className="flex items-center gap-1.5 text-xs text-slate-500">
              {rowsPerPageLabel}
              <select
                value={rowsPerPage}
                onChange={(event) => setRowsPerPage(event.target.value === 'all' ? 'all' : Number(event.target.value))}
                className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-600 focus:border-brand-500 focus:outline-none"
              >
                {pageSizeOptions.map((option) => (
                  <option key={option} value={option}>
                    {option === 'all' ? allRowsLabel : option}
                  </option>
                ))}
              </select>
            </label>
          )}
        </div>
      )}

      {isLoading ? (
        <p className="text-sm text-slate-500">{loadingText}</p>
      ) : sorted.length === 0 ? (
        emptyState ?? null
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="w-full text-start text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {columns.map((column) => {
                  const isActive = sortKey === column.key;
                  const alignClass = alignClasses[column.align ?? 'start'];

                  if (!column.sortable) {
                    return (
                      <th key={column.key} className={`px-5 py-3.5 ${alignClass} ${column.className ?? ''}`}>
                        {column.label}
                      </th>
                    );
                  }

                  const Icon = isActive ? (sortDirection === 'asc' ? ChevronUp : ChevronDown) : ChevronsUpDown;

                  return (
                    <th key={column.key} className={`px-5 py-3.5 ${alignClass} ${column.className ?? ''}`}>
                      <button
                        type="button"
                        onClick={() => toggleSort(column.key)}
                        className={`inline-flex select-none items-center gap-1 cursor-pointer hover:text-slate-700 ${
                          column.align === 'end' ? 'flex-row-reverse' : ''
                        }`}
                      >
                        {column.label}
                        <Icon className={`size-3.5 ${isActive ? 'text-brand-600' : 'text-slate-300'}`} />
                      </button>
                    </th>
                  );
                })}
                {actions && <th className="px-5 py-3.5" />}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {paginated.map((row) => (
                <tr key={rowKey(row)} className="hover:bg-slate-50/60">
                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={`px-5 py-3.5 text-slate-600 ${alignClasses[column.align ?? 'start']} ${column.className ?? ''}`}
                    >
                      {column.render ? column.render(row) : defaultRender(row, column.key)}
                    </td>
                  ))}
                  {actions && (
                    <td className="px-5 py-3.5">
                      <div className="flex justify-end gap-1.5">{actions(row)}</div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {!isLoading && sorted.length > 0 && (
        <div className="flex flex-wrap items-center justify-between gap-3 px-1 text-sm text-slate-500">
          {pageInfoLabel && <span>{pageInfoLabel(rangeFrom, rangeTo, sorted.length)}</span>}

          {totalPages > 1 && (
            <div className="ms-auto flex items-center gap-1.5">
              <button
                type="button"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={currentPage === 1}
                className="flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <ChevronLeft className="size-4 rtl:-scale-x-100" />
                {previousLabel}
              </button>
              <span className="px-1 text-xs text-slate-400">
                {currentPage} / {totalPages}
              </span>
              <button
                type="button"
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                disabled={currentPage === totalPages}
                className="flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                {nextLabel}
                <ChevronRight className="size-4 rtl:-scale-x-100" />
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
