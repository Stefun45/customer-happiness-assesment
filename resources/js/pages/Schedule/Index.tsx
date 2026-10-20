import React, { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '@/layouts/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { AlertTriangle, CalendarClock, CalendarCheck, Building2, UserX } from 'lucide-react'
import AccountManagerControl, { UserOption } from '@/components/AccountManagerControl'
import CompleteContactDialog from '@/components/CompleteContactDialog'

type Status = 'overdue' | 'due' | 'scheduled'

interface Row {
  id: number
  name: string
  company_name: string
  is_enterprise: boolean
  interval_months: number
  account_manager: UserOption | null
  last_contacted_at: string | null
  next_due_at: string
  status: Status
}

interface Props {
  rows: Row[]
  summary: { overdue: number; due: number; scheduled: number; unassigned: number }
  users: UserOption[]
  filters: { manager: string; status: 'overdue' | 'due' | 'all'; enterprise: boolean }
  intervals: { enterprise: number; standard: number }
}

function formatDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : null
}

function daysFromToday(iso: string) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return Math.round((new Date(iso).getTime() - today.getTime()) / (24 * 60 * 60 * 1000))
}

function StatusBadge({ status, dueAt }: { status: Status; dueAt: string }) {
  const days = daysFromToday(dueAt)
  if (status === 'overdue') {
    return <Badge variant="destructive">Overdue {Math.abs(days)}d</Badge>
  }
  if (status === 'due') {
    return <Badge variant="secondary">{days === 0 ? 'Due today' : `Due in ${days}d`}</Badge>
  }
  return <Badge variant="outline">Scheduled</Badge>
}

export default function ScheduleIndex({
  rows = [],
  summary = { overdue: 0, due: 0, scheduled: 0, unassigned: 0 },
  users = [],
  filters = { manager: 'me', status: 'due', enterprise: false },
  intervals = { enterprise: 1, standard: 4 },
}: Props) {
  const [completing, setCompleting] = useState<{ id: number; name: string } | null>(null)

  function applyFilters(overrides: Partial<Props['filters']>) {
    const next = { ...filters, ...overrides }
    router.get(
      '/schedule',
      {
        manager: next.manager !== 'me' ? next.manager : undefined,
        status: next.status !== 'due' ? next.status : undefined,
        enterprise: next.enterprise ? 1 : undefined,
      },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }

  const statusOptions: { key: Props['filters']['status']; label: string }[] = [
    { key: 'overdue', label: 'Overdue' },
    { key: 'due', label: 'Overdue and due soon' },
    { key: 'all', label: 'All' },
  ]

  return (
    <AppLayout title="Contact Schedule">
      <Head title="Contact Schedule" />

      <div className="grid gap-4 md:grid-cols-3 mb-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Overdue</CardTitle>
            <AlertTriangle className="h-4 w-4 text-destructive" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{summary.overdue}</div>
            <p className="text-xs text-muted-foreground">Past their contact date</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Due in the next 14 days</CardTitle>
            <CalendarClock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{summary.due}</div>
            <p className="text-xs text-muted-foreground">{summary.scheduled} further ahead</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Unassigned customers</CardTitle>
            <UserX className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{summary.unassigned}</div>
            <button className="text-xs text-primary hover:underline" onClick={() => applyFilters({ manager: 'unassigned', status: 'all' })}>
              Show unassigned
            </button>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <CardTitle>Contact Schedule</CardTitle>
              <CardDescription>
                Enterprise customers every {intervals.enterprise === 1 ? 'month' : `${intervals.enterprise} months`},
                others every {intervals.standard} months
              </CardDescription>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <select
                className="border rounded-md px-3 py-2 text-sm bg-background"
                value={filters.manager}
                onChange={(e) => applyFilters({ manager: e.target.value })}
              >
                <option value="me">My customers</option>
                <option value="all">All account managers</option>
                <option value="unassigned">Unassigned</option>
                {users.map((u) => (
                  <option key={u.id} value={String(u.id)}>
                    {u.name}
                  </option>
                ))}
              </select>
              {statusOptions.map((s) => (
                <Button
                  key={s.key}
                  variant={filters.status === s.key ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => applyFilters({ status: s.key })}
                >
                  {s.label}
                </Button>
              ))}
              <Button
                variant={filters.enterprise ? 'default' : 'outline'}
                size="sm"
                onClick={() => applyFilters({ enterprise: !filters.enterprise })}
              >
                <Building2 className="h-4 w-4 mr-1" />
                Enterprise
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {rows.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              <CalendarCheck className="h-12 w-12 mx-auto mb-4 opacity-30" />
              <p className="text-lg font-medium">Nothing to show</p>
              <p className="text-sm">
                {filters.manager === 'me'
                  ? 'No customers assigned to you match this filter.'
                  : 'No customers match this filter.'}
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Customer</TableHead>
                    <TableHead>Account manager</TableHead>
                    <TableHead>Frequency</TableHead>
                    <TableHead>Last contacted</TableHead>
                    <TableHead>Next due</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>
                        <Link href={`/clients/${row.id}`} className="font-medium hover:underline">
                          {row.name}
                        </Link>
                        {row.is_enterprise && (
                          <Badge variant="default" className="ml-2 text-xs">Enterprise</Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        <AccountManagerControl clientId={row.id} current={row.account_manager} users={users} />
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {row.interval_months === 1 ? 'Monthly' : `Every ${row.interval_months} months`}
                      </TableCell>
                      <TableCell className="text-sm">
                        {formatDate(row.last_contacted_at) ?? <span className="text-muted-foreground">Never</span>}
                      </TableCell>
                      <TableCell className="text-sm">{formatDate(row.next_due_at)}</TableCell>
                      <TableCell>
                        <StatusBadge status={row.status} dueAt={row.next_due_at} />
                      </TableCell>
                      <TableCell className="text-right">
                        <Button size="sm" variant="outline" onClick={() => setCompleting({ id: row.id, name: row.name })}>
                          <CalendarCheck className="h-4 w-4 mr-1" />
                          Mark contacted
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <CompleteContactDialog client={completing} onOpenChange={(open) => !open && setCompleting(null)} />
    </AppLayout>
  )
}
