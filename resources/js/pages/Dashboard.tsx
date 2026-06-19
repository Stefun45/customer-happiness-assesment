import React from 'react'
import { Head, Link } from '@inertiajs/react'
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
import { Users, AlertTriangle, TrendingUp, Receipt, Eye } from 'lucide-react'

interface HappinessScore {
  score: number
  churn_risk: 'low' | 'medium' | 'high'
}

interface Client {
  id: number
  name: string
  company_name: string
  last_contact: string | null
  outstanding_invoices_amount: number
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

export default function Dashboard({ stats = mockStats, clients = mockClients }: Props) {
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
          <CardTitle>Client Overview</CardTitle>
          <CardDescription>
            All clients with their latest happiness scores and churn risk indicators.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {clients.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
              <Users className="h-12 w-12 mb-4 opacity-30" />
              <p className="text-lg font-medium">No clients yet</p>
              <p className="text-sm">Sync data from your integrations to see clients here.</p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Client</TableHead>
                  <TableHead>Happiness Score</TableHead>
                  <TableHead>Churn Risk</TableHead>
                  <TableHead>Last Contact</TableHead>
                  <TableHead>Outstanding</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {clients.map((client) => (
                  <TableRow key={client.id}>
                    <TableCell>
                      <div>
                        <p className="font-medium">{client.name}</p>
                        <p className="text-xs text-muted-foreground">{client.company_name}</p>
                      </div>
                    </TableCell>
                    <TableCell>
                      {client.latest_score ? (
                        <Badge
                          variant={happinessBadgeVariant(client.latest_score.score)}
                        >
                          {client.latest_score.score.toFixed(1)} / 10
                        </Badge>
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
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm" asChild>
                        <Link href={`/clients/${client.id}`}>
                          <Eye className="h-4 w-4 mr-1" />
                          View
                        </Link>
                      </Button>
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
