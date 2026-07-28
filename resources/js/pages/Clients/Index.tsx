import React, { useEffect, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import AppLayout from '@/layouts/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Users, Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react'

interface HappinessScore {
  score: number
  churn_risk: 'low' | 'medium' | 'high'
}

interface Client {
  id: number
  name: string
  email: string | null
  company_name: string
  phone: string | null
  is_new_customer: boolean
  is_enterprise: boolean
  latest_score: HappinessScore | null
  created_at: string
}

interface Pagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

interface Props {
  clients: {
    data: Client[]
    meta: Pagination
  }
  show_lost: boolean
  filters: { search: string; sort: string; direction: string }
}

function churnBadge(risk: string) {
  const variants: Record<string, 'default' | 'secondary' | 'destructive'> = {
    low: 'default',
    medium: 'secondary',
    high: 'destructive',
  }
  return variants[risk] ?? 'secondary'
}

function scoreBadge(score: number): 'default' | 'secondary' | 'destructive' {
  if (score >= 7) return 'default'
  if (score >= 4) return 'secondary'
  return 'destructive'
}

const emptyClients = { data: [], meta: { current_page: 1, last_page: 1, per_page: 50, total: 0 } }

export default function ClientsIndex({
  clients = emptyClients,
  show_lost = false,
  filters = { search: '', sort: 'name', direction: 'asc' },
}: Props) {
  const [search, setSearch] = useState(filters.search)

  function sortBy(col: string) {
    const direction =
      filters.sort === col && filters.direction === 'asc' ? 'desc' : 'asc'
    router.get(
      '/clients',
      {
        sort: col,
        direction,
        search: filters.search || undefined,
        lost: show_lost ? 1 : undefined,
      },
      { preserveState: true, replace: true }
    )
  }

  function SortIcon({ col }: { col: string }) {
    if (filters.sort !== col) return <ArrowUpDown className="h-3 w-3 ml-1 opacity-40" />
    return filters.direction === 'asc'
      ? <ArrowUp className="h-3 w-3 ml-1" />
      : <ArrowDown className="h-3 w-3 ml-1" />
  }

  // Debounce search — reset to page 1 when query changes
  useEffect(() => {
    const timer = setTimeout(() => {
      if (search !== filters.search) {
        router.get(
          '/clients',
          { search: search || undefined, lost: show_lost ? 1 : undefined },
          { preserveState: true, replace: true }
        )
      }
    }, 400)
    return () => clearTimeout(timer)
  }, [search])

  function toggleLost() {
    router.get('/clients', show_lost ? {} : { lost: 1 }, { preserveState: false })
  }

  function goToPage(page: number) {
    router.get(
      '/clients',
      {
        page,
        search: filters.search || undefined,
        sort: filters.sort !== 'name' ? filters.sort : undefined,
        direction: filters.direction !== 'asc' ? filters.direction : undefined,
        lost: show_lost ? 1 : undefined,
      },
      { preserveState: true, preserveScroll: true }
    )
  }

  const filtered = clients.data

  return (
    <AppLayout title="Clients">
      <Head title="Clients" />

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>{show_lost ? 'Lost Clients' : 'Active Clients'}</CardTitle>
              <CardDescription>
                {clients.meta.total} client{clients.meta.total !== 1 ? 's' : ''}
                {filters.search ? ` matching "${filters.search}"` : ' total'}
              </CardDescription>
            </div>
            <Button variant={show_lost ? 'default' : 'outline'} onClick={toggleLost}>
              <Users className="h-4 w-4 mr-2" />
              {show_lost ? 'View Active' : 'View Lost'}
            </Button>
          </div>
          <div className="relative mt-4">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search by name, company or email..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9"
            />
          </div>
        </CardHeader>
        <CardContent>
          {filtered.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
              <Users className="h-12 w-12 mb-4 opacity-30" />
              <p className="text-lg font-medium">
                {search ? 'No clients match your search' : 'No clients yet'}
              </p>
            </div>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="cursor-pointer select-none" onClick={() => sortBy('name')}>
                      <span className="flex items-center">Name <SortIcon col="name" /></span>
                    </TableHead>
                    <TableHead>Company</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead className="cursor-pointer select-none" onClick={() => sortBy('happiness')}>
                      <span className="flex items-center">Happiness <SortIcon col="happiness" /></span>
                    </TableHead>
                    <TableHead className="cursor-pointer select-none" onClick={() => sortBy('churn_risk')}>
                      <span className="flex items-center">Churn Risk <SortIcon col="churn_risk" /></span>
                    </TableHead>
                    <TableHead>Type</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filtered.map((client) => (
                    <TableRow
                      key={client.id}
                      className="cursor-pointer hover:bg-muted/50"
                      onClick={() => router.visit(`/clients/${client.id}`)}
                    >
                      <TableCell className="font-medium">{client.name}</TableCell>
                      <TableCell>{client.company_name}</TableCell>
                      <TableCell className="text-muted-foreground">{client.email ?? '—'}</TableCell>
                      <TableCell>
                        {client.latest_score ? (
                          <Badge variant={scoreBadge(client.latest_score.score)}>
                            {client.latest_score.score.toFixed(1)}
                          </Badge>
                        ) : (
                          <span className="text-xs text-muted-foreground">—</span>
                        )}
                      </TableCell>
                      <TableCell>
                        {client.latest_score ? (
                          <Badge
                            variant={churnBadge(client.latest_score.churn_risk)}
                            className="capitalize"
                          >
                            {client.latest_score.churn_risk}
                          </Badge>
                        ) : (
                          <span className="text-xs text-muted-foreground">—</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1 flex-wrap">
                          {client.is_enterprise && (
                            <Badge variant="default">Enterprise</Badge>
                          )}
                          {client.is_new_customer ? (
                            <Badge variant="secondary">New</Badge>
                          ) : (
                            <Badge variant="outline">Existing</Badge>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {clients.meta.last_page > 1 && (
                <div className="flex items-center justify-between mt-4 text-sm text-muted-foreground">
                  <span>
                    Page {clients.meta.current_page} of {clients.meta.last_page}
                    {' '}({clients.meta.total} total)
                  </span>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={clients.meta.current_page <= 1}
                      onClick={() => goToPage(clients.meta.current_page - 1)}
                    >
                      Previous
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={clients.meta.current_page >= clients.meta.last_page}
                      onClick={() => goToPage(clients.meta.current_page + 1)}
                    >
                      Next
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </AppLayout>
  )
}
