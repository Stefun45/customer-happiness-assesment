import React from 'react'
import { Head, Link, router } from '@inertiajs/react'
import AppLayout from '@/layouts/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { Receipt } from 'lucide-react'

interface Invoice {
  id: number
  invoice_number: string | null
  amount_pence: number
  currency: string
  status: string | null
  issued_at: string | null
  due_at: string | null
  paid_at: string | null
  is_overdue: boolean
  client: { id: number; name: string } | null
}

interface Props {
  invoices: {
    data: Invoice[]
    meta: { current_page: number; last_page: number; total: number }
  }
  filter: string
}

const filters = [
  { key: 'outstanding', label: 'Outstanding' },
  { key: 'overdue',     label: 'Overdue' },
  { key: 'all',         label: 'All' },
]

function formatAmount(pence: number, currency: string) {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: currency || 'GBP',
  }).format(pence / 100)
}

function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const empty = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }

export default function InvoicesIndex({ invoices = empty, filter = 'outstanding' }: Props) {
  const totalOutstanding = invoices.data.reduce((sum, i) => sum + i.amount_pence, 0)
  const currency = invoices.data[0]?.currency || 'GBP'

  return (
    <AppLayout title="Invoices">
      <Head title="Invoices" />

      <Card>
        <CardHeader>
          <div className="flex items-start justify-between">
            <div>
              <CardTitle>Invoices</CardTitle>
              <CardDescription>
                {invoices.meta.total} invoice{invoices.meta.total !== 1 ? 's' : ''}
                {invoices.data.length > 0 && (
                  <span className="ml-2 font-medium text-foreground">
                    — {formatAmount(totalOutstanding, currency)} total
                  </span>
                )}
              </CardDescription>
            </div>

            <div className="flex gap-1">
              {filters.map((f) => (
                <Button
                  key={f.key}
                  size="sm"
                  variant={filter === f.key ? 'default' : 'outline'}
                  onClick={() => router.get('/invoices', { filter: f.key })}
                >
                  {f.label}
                </Button>
              ))}
            </div>
          </div>
        </CardHeader>

        <CardContent>
          {invoices.data.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
              <Receipt className="h-12 w-12 mb-4 opacity-30" />
              <p className="text-lg font-medium">No invoices found</p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Client</TableHead>
                  <TableHead>Invoice #</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Issued</TableHead>
                  <TableHead>Due</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {invoices.data.map((inv) => (
                  <TableRow key={inv.id}>
                    <TableCell className="font-medium">
                      {inv.client ? (
                        <Link href={`/clients/${inv.client.id}`} className="hover:underline">
                          {inv.client.name}
                        </Link>
                      ) : (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {inv.invoice_number ?? '—'}
                    </TableCell>
                    <TableCell className="font-medium">
                      {formatAmount(inv.amount_pence, inv.currency)}
                    </TableCell>
                    <TableCell>
                      {inv.is_overdue ? (
                        <Badge variant="destructive">Overdue</Badge>
                      ) : (
                        <Badge variant="outline">{inv.status ?? 'Outstanding'}</Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-muted-foreground text-sm">
                      {formatDate(inv.issued_at)}
                    </TableCell>
                    <TableCell className={`text-sm ${inv.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground'}`}>
                      {formatDate(inv.due_at)}
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
