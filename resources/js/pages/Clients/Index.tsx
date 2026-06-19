import React, { useState } from 'react'
import { Head, Link } from '@inertiajs/react'
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
import { Users, Search, Eye } from 'lucide-react'

interface HappinessScore {
  score: number
  churn_risk: 'low' | 'medium' | 'high'
}

interface Client {
  id: number
  name: string
  email: string
  company_name: string
  phone: string | null
  is_new_customer: boolean
  latest_score: HappinessScore | null
  created_at: string
}

interface Pagination {
  current_page: number
  last_page: number
  total: number
}

interface Props {
  clients: {
    data: Client[]
    meta: Pagination
  }
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

export default function ClientsIndex({ clients = emptyClients }: Props) {
  const [search, setSearch] = useState('')

  const filtered = clients.data.filter(
    (c) =>
      c.name.toLowerCase().includes(search.toLowerCase()) ||
      c.company_name.toLowerCase().includes(search.toLowerCase()) ||
      c.email.toLowerCase().includes(search.toLowerCase())
  )

  return (
    <AppLayout title="Clients">
      <Head title="Clients" />

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>All Clients</CardTitle>
              <CardDescription>
                {clients.meta.total} client{clients.meta.total !== 1 ? 's' : ''} total
              </CardDescription>
            </div>
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
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Company</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Happiness</TableHead>
                  <TableHead>Churn Risk</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((client) => (
                  <TableRow key={client.id}>
                    <TableCell className="font-medium">{client.name}</TableCell>
                    <TableCell>{client.company_name}</TableCell>
                    <TableCell className="text-muted-foreground">{client.email}</TableCell>
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
