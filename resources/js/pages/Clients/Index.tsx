import React, { useState } from 'react'
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
import { Users, Search } from 'lucide-react'

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

const emptyClients = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }

export default function ClientsIndex({ clients = emptyClients, show_lost = false }: Props) {
  const [search, setSearch] = useState('')

  function toggleLost() {
    router.get('/clients', show_lost ? {} : { lost: 1 }, { preserveState: false })
  }

  function goToPage(page: number) {
    const params: Record<string, string | number> = { page }
    if (show_lost) params.lost = 1
    if (search) params.search = search
    router.get('/clients', params, { preserveState: true, preserveScroll: true })
  }

  const filtered = clients.data.filter(
    (c) =>
      c.name.toLowerCase().includes(search.toLowerCase()) ||
      c.company_name.toLowerCase().includes(search.toLowerCase()) ||
      (c.email ?? '').toLowerCase().includes(search.toLowerCase())
  )

  return (
    <AppLayout title="Clients">
      <Head title="Clients" />

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>{show_lost ? 'Lost Clients' : 'Active Clients'}</CardTitle>
              <CardDescription>
                {clients.meta.total} client{clients.meta.total !== 1 ? 's' : ''} total
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
                    <TableHead>Name</TableHead>
                    <TableHead>Company</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Happiness</TableHead>
                    <TableHead>Churn Risk</TableHead>
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
                        {client.is_new_customer ? (
                          <Badge variant="secondary">New</Badge>
                        ) : (
                          <Badge variant="outline">Existing</Badge>
                        )}
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
