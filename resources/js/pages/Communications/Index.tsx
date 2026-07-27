import React, { useEffect, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
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
import { MessageSquare, Search } from 'lucide-react'

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
  filters: {
    search: string
    source: string
  }
}

const sourceLabel: Record<string, string> = {
  freshdesk: 'Support',
  fireflies: 'Call',
  onboarding_helpdesk: 'Onboarding',
  happiness_review: 'Review',
}

const sourceTabs = [
  { value: '', label: 'All' },
  { value: 'freshdesk', label: 'Support' },
  { value: 'fireflies', label: 'Calls' },
  { value: 'happiness_review', label: 'Reviews' },
  { value: 'onboarding_helpdesk', label: 'Onboarding' },
]

const empty = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }

export default function CommunicationsIndex({
  communications = empty,
  filters = { search: '', source: '' },
}: Props) {
  const [search, setSearch] = useState(filters.search)

  // Debounce search — fire server request 400ms after user stops typing
  useEffect(() => {
    const timer = setTimeout(() => {
      if (search !== filters.search) {
        router.get(
          '/communications',
          { search: search || undefined, source: filters.source || undefined },
          { preserveState: true, replace: true }
        )
      }
    }, 400)
    return () => clearTimeout(timer)
  }, [search])

  function setSource(source: string) {
    router.get(
      '/communications',
      { source: source || undefined, search: search || undefined },
      { preserveState: true, replace: true }
    )
  }

  function goToPage(page: number) {
    router.get(
      '/communications',
      {
        page,
        search: filters.search || undefined,
        source: filters.source || undefined,
      },
      { preserveState: true, preserveScroll: true }
    )
  }

  return (
    <AppLayout title="Communications">
      <Head title="Communications" />

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>All Communications</CardTitle>
              <CardDescription>
                {communications.meta.total} communication{communications.meta.total !== 1 ? 's' : ''}
                {filters.source ? ` · ${sourceLabel[filters.source] ?? filters.source}` : ''}
                {filters.search ? ` matching "${filters.search}"` : ''}
              </CardDescription>
            </div>
          </div>

          {/* Source filter tabs */}
          <div className="flex gap-2 mt-4 flex-wrap">
            {sourceTabs.map((tab) => (
              <Button
                key={tab.value}
                variant={filters.source === tab.value ? 'default' : 'outline'}
                size="sm"
                onClick={() => setSource(tab.value)}
              >
                {tab.label}
              </Button>
            ))}
          </div>

          {/* Search */}
          <div className="relative mt-2">
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
          {communications.data.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
              <MessageSquare className="h-12 w-12 mb-4 opacity-30" />
              <p className="text-lg font-medium">
                {filters.search || filters.source
                  ? 'No communications match your filters'
                  : 'No communications yet'}
              </p>
            </div>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Client</TableHead>
                    <TableHead>Source</TableHead>
                    <TableHead>Subject</TableHead>
                    <TableHead>Preview</TableHead>
                    <TableHead>Date</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {communications.data.map((comm) => (
                    <TableRow key={comm.id}>
                      <TableCell className="font-medium">
                        {comm.client ? (
                          <Link href={`/clients/${comm.client.id}`} className="hover:underline">
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
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {communications.meta.last_page > 1 && (
                <div className="flex items-center justify-between mt-4 text-sm text-muted-foreground">
                  <span>
                    Page {communications.meta.current_page} of {communications.meta.last_page}
                    {' '}({communications.meta.total} total)
                  </span>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={communications.meta.current_page <= 1}
                      onClick={() => goToPage(communications.meta.current_page - 1)}
                    >
                      Previous
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={communications.meta.current_page >= communications.meta.last_page}
                      onClick={() => goToPage(communications.meta.current_page + 1)}
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
