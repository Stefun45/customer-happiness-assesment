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
import { MessageSquare, Search, Eye } from 'lucide-react'

interface Communication {
  id: number
  source: string
  subject: string | null
  body: string
  occurred_at: string | null
  client: { id: number; name: string } | null
}

interface Pagination {
  current_page: number
  last_page: number
  total: number
}

interface Props {
  communications: {
    data: Communication[]
    meta: Pagination
  }
}

const sourceLabel: Record<string, string> = {
  freshdesk: 'Support',
  fireflies: 'Call',
  onboarding_helpdesk: 'Onboarding',
  happiness_review: 'Review',
}

const empty = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }

export default function CommunicationsIndex({ communications = empty }: Props) {
  const [search, setSearch] = useState('')

  const filtered = communications.data.filter(
    (c) =>
      (c.subject ?? '').toLowerCase().includes(search.toLowerCase()) ||
      (c.client?.name ?? '').toLowerCase().includes(search.toLowerCase()) ||
      c.body.toLowerCase().includes(search.toLowerCase())
  )

  return (
    <AppLayout title="Communications">
      <Head title="Communications" />

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>All Communications</CardTitle>
              <CardDescription>
                {communications.meta.total} communication{communications.meta.total !== 1 ? 's' : ''} total
              </CardDescription>
            </div>
          </div>
          <div className="relative mt-4">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search by client, subject or content..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9"
            />
          </div>
        </CardHeader>
        <CardContent>
          {filtered.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
              <MessageSquare className="h-12 w-12 mb-4 opacity-30" />
              <p className="text-lg font-medium">
                {search ? 'No communications match your search' : 'No communications yet'}
              </p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Client</TableHead>
                  <TableHead>Source</TableHead>
                  <TableHead>Subject</TableHead>
                  <TableHead>Preview</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((comm) => (
                  <TableRow key={comm.id}>
                    <TableCell className="font-medium">
                      {comm.client ? (
                        <Link
                          href={`/clients/${comm.client.id}`}
                          className="hover:underline"
                        >
                          {comm.client.name}
                        </Link>
                      ) : (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">
                        {sourceLabel[comm.source] ?? comm.source}
                      </Badge>
                    </TableCell>
                    <TableCell className="max-w-[200px] truncate">
                      {comm.subject ?? <span className="text-muted-foreground">—</span>}
                    </TableCell>
                    <TableCell className="max-w-[300px] truncate text-muted-foreground text-sm">
                      {comm.body}
                    </TableCell>
                    <TableCell className="text-muted-foreground text-sm whitespace-nowrap">
                      {comm.occurred_at
                        ? new Date(comm.occurred_at).toLocaleDateString('en-GB')
                        : '—'}
                    </TableCell>
                    <TableCell className="text-right">
                      {comm.client && (
                        <Button variant="ghost" size="sm" asChild>
                          <Link href={`/clients/${comm.client.id}`}>
                            <Eye className="h-4 w-4 mr-1" />
                            View Client
                          </Link>
                        </Button>
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
