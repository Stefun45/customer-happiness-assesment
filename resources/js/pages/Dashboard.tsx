import React from 'react'
import { Head, router } from '@inertiajs/react'
import AppLayout from '@/layouts/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Users, AlertTriangle, TrendingUp, Receipt } from 'lucide-react'

interface HappinessScore {
  score: number
  churn_risk: 'low' | 'medium' | 'high'
  source: 'ai' | 'account_manager'
}

interface Client {
  id: number
  name: string
  company_name: string
  last_contact: string | null
  outstanding_invoices_amount: number
  account_manager: string | null
  latest_score: HappinessScore | null
}

interface DashboardStats {
  total_clients: number
  at_risk_clients: number
  average_happiness_score: number
  outstanding_invoices_count: number
}

interface Props {
  stats: DashboardStats
  clients: Client[]
  // manager: "me", "all", "unassigned" or a user id
  filters: { manager: string }
  users: { id: number; name: string }[]
}

function happinessBadgeVariant(score: number): 'default' | 'secondary' | 'destructive' {
  if (score >= 7) return 'default'
  if (score >= 4) return 'secondary'
  return 'destructive'
}

function churnRiskBadgeVariant(risk: string): 'default' | 'secondary' | 'destructive' {
  if (risk === 'low') return 'default'
  if (risk === 'medium') return 'secondary'
  return 'destructive'
}

function formatCurrency(pence: number): string {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(pence / 100)
}

const mockStats: DashboardStats = {
  total_clients: 0,
  at_risk_clients: 0,
  average_happiness_score: 0,
  outstanding_invoices_count: 0,
}

const mockClients: Client[] = []

export default function Dashboard({
  stats = mockStats,
  clients = mockClients,
  filters = { manager: 'all' },
  users = [],
}: Props) {
  function filterByManager(manager: string) {
    router.get('/dashboard', { manager }, { preserveState: true, preserveScroll: true, replace: true })
  }

  const managerName = users.find((u) => String(u.id) === filters.manager)?.name
  const overviewScope =
    filters.manager === 'me' ? 'Your customers'
    : filters.manager === 'unassigned' ? 'Unassigned customers'
    : managerName ? `${managerName}'s customers`
    : 'All customers'

  return (
    <AppLayout title="Dashboard">
      <Head title="Dashboard" />

      {/* Summary cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-8">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Clients</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.total_clients}</div>
            <p className="text-xs text-muted-foreground">Active accounts</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">At-Risk Clients</CardTitle>
            <AlertTriangle className="h-4 w-4 text-destructive" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-destructive">{stats.at_risk_clients}</div>
            <p className="text-xs text-muted-foreground">High churn risk</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Avg Happiness Score</CardTitle>
            <TrendingUp className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {stats.average_happiness_score > 0
                ? stats.average_happiness_score.toFixed(1)
                : '—'}
            </div>
            <p className="text-xs text-muted-foreground">Out of 10</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Outstanding Invoices</CardTitle>
            <Receipt className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.outstanding_invoices_count}</div>
            <p className="text-xs text-muted-foreground">Unpaid invoices</p>
          </CardContent>
        </Card>
      </div>

      {/* Client table */}
      <Card>
        <CardHeader>
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <CardTitle>Client Overview</CardTitle>
              <CardDescription>
                {overviewScope}, ordered by churn risk — highest risk first.
              </CardDescription>
            </div>
            <select
              aria-label="Filter by account manager"
              className="border rounded-md px-3 py-2 text-sm bg-background"
              value={filters.manager}
              onChange={(e) => filterByManager(e.target.value)}
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
          </div>
        </CardHeader>
        <CardContent>
          {clients.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
              <Users className="h-12 w-12 mb-4 opacity-30" />
              {filters.manager === 'me' ? (
                <>
                  <p className="text-lg font-medium">No customers assigned to you</p>
                  <p className="text-sm">
                    Pick up customers from the{' '}
                    <a href="/schedule?manager=unassigned&status=all" className="text-primary hover:underline">
                      unassigned list
                    </a>
                    .
                  </p>
                </>
              ) : filters.manager !== 'all' ? (
                <p className="text-lg font-medium">No customers match this filter</p>
              ) : (
                <>
                  <p className="text-lg font-medium">No clients yet</p>
                  <p className="text-sm">Sync data from your integrations to see clients here.</p>
                </>
              )}
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Client</TableHead>
                  <TableHead>Account Manager</TableHead>
                  <TableHead>Happiness Score</TableHead>
                  <TableHead>Churn Risk</TableHead>
                  <TableHead>Last Contact</TableHead>
                  <TableHead>Outstanding</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {clients.map((client) => (
                  <TableRow
                    key={client.id}
                    className="cursor-pointer hover:bg-muted/50"
                    onClick={() => router.visit(`/clients/${client.id}`)}
                  >
                    <TableCell>
                      <div>
                        <p className="font-medium">{client.name}</p>
                        <p className="text-xs text-muted-foreground">{client.company_name}</p>
                      </div>
                    </TableCell>
                    <TableCell>
                      {client.account_manager ?? (
                        <span className="text-muted-foreground text-xs">Unassigned</span>
                      )}
                    </TableCell>
                    <TableCell>
                      {client.latest_score ? (
                        <span className="inline-flex items-center gap-1.5">
                          <Badge variant={happinessBadgeVariant(client.latest_score.score)}>
                            {client.latest_score.score.toFixed(1)} / 10
                          </Badge>
                          {client.latest_score.source === 'account_manager' && (
                            <span className="text-xs text-muted-foreground" title="Assessed by the account manager">AM</span>
                          )}
                        </span>
                      ) : (
                        <span className="text-muted-foreground text-xs">Not scored</span>
                      )}
                    </TableCell>
                    <TableCell>
                      {client.latest_score ? (
                        <Badge
                          variant={churnRiskBadgeVariant(client.latest_score.churn_risk)}
                          className="capitalize"
                        >
                          {client.latest_score.churn_risk}
                        </Badge>
                      ) : (
                        <span className="text-muted-foreground text-xs">Unknown</span>
                      )}
                    </TableCell>
                    <TableCell>
                      {client.last_contact ? (
                        <span className="text-sm">
                          {new Date(client.last_contact).toLocaleDateString('en-GB')}
                        </span>
                      ) : (
                        <span className="text-muted-foreground text-xs">Never</span>
                      )}
                    </TableCell>
                    <TableCell>
                      {client.outstanding_invoices_amount > 0 ? (
                        <span className="font-medium text-destructive">
                          {formatCurrency(client.outstanding_invoices_amount)}
                        </span>
                      ) : (
                        <span className="text-muted-foreground text-xs">None</span>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </AppLayout>
  )
}
