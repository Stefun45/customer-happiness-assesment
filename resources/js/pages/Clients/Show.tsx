import React from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import AppLayout from '@/layouts/AppLayout'
import AccountManagerControl, { UserOption } from '@/components/AccountManagerControl'
import CompleteContactDialog, { FirefliesCall } from '@/components/CompleteContactDialog'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Progress } from '@/components/ui/progress'
import { Separator } from '@/components/ui/separator'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  RefreshCw,
  Mail,
  Phone,
  Building,
  MessageSquare,
  Receipt,
  Brain,
  AlertTriangle,
  CheckCircle,
  UserX,
  RotateCcw,
  Users,
  Star,
  CalendarCheck,
  StickyNote,
  Trash2,
  Video,
} from 'lucide-react'

interface Client {
  id: number
  name: string
  email: string | null
  phone: string | null
  company_name: string
  is_new_customer: boolean
  is_enterprise: boolean
  freshdesk_id: string | null
  freeagent_contact_id: string | null
  lost_at: string | null
  lost_reason: string | null
  account_manager: UserOption | null
}

interface Schedule {
  interval_months: number
  last_contacted_at: string | null
  next_due_at: string
  status: 'overdue' | 'due' | 'scheduled'
}

interface ContactLog {
  id: number
  contacted_at: string
  notes: string | null
  user: UserOption | null
  communication: { id: number; subject: string | null; occurred_at: string | null } | null
}

interface Note {
  id: number
  body: string
  user: UserOption | null
  created_at: string
}

interface Communication {
  id: number
  source: 'freshdesk' | 'fireflies' | 'onboarding_helpdesk'
  subject: string | null
  body: string
  occurred_at: string
  sentiment_score: number | null
}

interface Invoice {
  id: number
  invoice_number: string
  amount_pence: number
  currency: string
  status: string
  issued_at: string
  due_at: string
  paid_at: string | null
  view_url: string | null
}

interface HappinessScore {
  id: number
  score: number
  churn_risk: 'low' | 'medium' | 'high'
  analysis_summary: string
  key_concerns: string[]
  recommended_actions: string[]
  scored_at: string
}

interface Contact {
  id: number
  name: string | null
  email: string | null
  phone: string | null
  latest_review: {
    id: number
    score: number | null
    occurred_at: string
  } | null
}

interface Props {
  client: Client
  communications: Communication[]
  invoices: Invoice[]
  latest_score: HappinessScore | null
  score_history: HappinessScore[]
  contacts: Contact[]
  schedule: Schedule
  contact_logs: ContactLog[]
  notes: Note[]
  fireflies_calls: FirefliesCall[]
  users: UserOption[]
}

function formatDay(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : null
}

function sourceBadge(source: string) {
  const map: Record<string, string> = {
    freshdesk: 'Support',
    fireflies: 'Call',
    onboarding_helpdesk: 'Onboarding',
  }
  return map[source] ?? source
}

function formatCurrency(pence: number, currency = 'GBP'): string {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(pence / 100)
}

function ScoreBar({ score }: { score: number }) {
  const pct = (score / 10) * 100
  return (
    <div className="space-y-2">
      <div className="flex justify-between text-sm">
        <span className="text-muted-foreground">Happiness Score</span>
        <span className="font-bold">{score.toFixed(1)} / 10</span>
      </div>
      <Progress value={pct} className="h-3" />
    </div>
  )
}

const emptyClient: Client = {
  id: 0,
  name: '',
  email: null,
  phone: null,
  company_name: '',
  is_new_customer: false,
  is_enterprise: false,
  freshdesk_id: null,
  freeagent_contact_id: null,
  lost_at: null,
  lost_reason: null,
  account_manager: null,
}

export default function ClientShow({
  client = emptyClient,
  communications = [],
  invoices = [],
  latest_score = null,
  score_history = [],
  contacts = [],
  schedule = { interval_months: 4, last_contacted_at: null, next_due_at: new Date().toISOString(), status: 'overdue' },
  contact_logs = [],
  notes = [],
  fireflies_calls = [],
  users = [],
}: Props) {
  const { auth } = usePage<{ auth: { user: { id: number; role: string } | null } }>().props
  const canDelete = (owner: UserOption | null) => auth.user?.role === 'admin' || owner?.id === auth.user?.id
  const [completingContact, setCompletingContact] = React.useState(false)
  const [noteBody, setNoteBody] = React.useState('')
  const [savingNote, setSavingNote] = React.useState(false)

  function addNote(e: React.FormEvent) {
    e.preventDefault()
    if (!noteBody.trim()) return
    setSavingNote(true)
    router.post(`/clients/${client.id}/notes`, { body: noteBody }, {
      preserveScroll: true,
      onSuccess: () => setNoteBody(''),
      onFinish: () => setSavingNote(false),
    })
  }

  function deleteNote(id: number) {
    if (confirm('Delete this note?')) router.delete(`/notes/${id}`, { preserveScroll: true })
  }

  function deleteContactLog(id: number) {
    if (confirm('Remove this completed contact? The schedule will be recalculated.')) {
      router.delete(`/contact-logs/${id}`, { preserveScroll: true })
    }
  }
  const [analysing, setAnalysing] = React.useState(false)
  const [lostReason, setLostReason] = React.useState('')
  const [showLostForm, setShowLostForm] = React.useState(false)

  function triggerAnalysis() {
    setAnalysing(true)
    router.post(`/clients/${client.id}/analyse`, {}, { onFinish: () => setAnalysing(false) })
  }

  function markAsLost() {
    router.post(`/clients/${client.id}/mark-as-lost`, { reason: lostReason }, {
      onSuccess: () => { setShowLostForm(false); setLostReason('') },
    })
  }

  function restore() {
    router.post(`/clients/${client.id}/restore`)
  }

  const outstandingInvoices = invoices.filter((i) => !i.paid_at)

  return (
    <AppLayout title={client.name}>
      <Head title={client.name} />

      {/* Lost banner */}
      {client.lost_at && (
        <Alert variant="destructive" className="mb-6">
          <UserX className="h-4 w-4" />
          <AlertTitle>Customer Lost — {new Date(client.lost_at).toLocaleDateString('en-GB')}</AlertTitle>
          {client.lost_reason && <AlertDescription>{client.lost_reason}</AlertDescription>}
        </Alert>
      )}

      {/* Header */}
      <div className="flex items-start justify-between mb-6">
        <div>
          <h2 className="text-2xl font-bold">{client.name}</h2>
          <p className="text-muted-foreground">{client.company_name}</p>
          <div className="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
            {client.email && (
              <span className="flex items-center gap-1">
                <Mail className="h-3 w-3" />
                {client.email}
              </span>
            )}
            {client.phone && (
              <span className="flex items-center gap-1">
                <Phone className="h-3 w-3" />
                {client.phone}
              </span>
            )}
            <span className="flex items-center gap-1">
              <Building className="h-3 w-3" />
              {client.is_new_customer ? 'New customer' : 'Existing customer'}
            </span>
            {client.is_enterprise && (
              <Badge variant="default">Enterprise</Badge>
            )}
          </div>
          <div className="flex items-center gap-2 mt-3 text-sm">
            <span className="text-muted-foreground">Account manager:</span>
            <AccountManagerControl clientId={client.id} current={client.account_manager} users={users} />
          </div>
        </div>
        <div className="flex items-center gap-2">
          {client.lost_at ? (
            <Button variant="outline" onClick={restore}>
              <RotateCcw className="h-4 w-4 mr-2" />
              Restore as Active
            </Button>
          ) : (
            <>
              {showLostForm ? (
                <div className="flex items-start gap-2">
                  <textarea
                    className="border rounded px-3 py-1.5 text-sm w-80 bg-background resize-none"
                    placeholder="Reason (optional)"
                    rows={3}
                    value={lostReason}
                    onChange={(e) => setLostReason(e.target.value)}
                  />
                  <div className="flex flex-col gap-1">
                    <Button variant="destructive" size="sm" onClick={markAsLost}>Confirm</Button>
                    <Button variant="ghost" size="sm" onClick={() => setShowLostForm(false)}>Cancel</Button>
                  </div>
                </div>
              ) : (
                <Button variant="outline" onClick={() => setShowLostForm(true)}>
                  <UserX className="h-4 w-4 mr-2" />
                  Mark as Lost
                </Button>
              )}
            </>
          )}
          {!client.lost_at && (
            <Button variant="outline" onClick={() => setCompletingContact(true)}>
              <CalendarCheck className="h-4 w-4 mr-2" />
              Mark contacted
            </Button>
          )}
          <Button onClick={triggerAnalysis} disabled={analysing}>
            <RefreshCw className={`h-4 w-4 mr-2 ${analysing ? 'animate-spin' : ''}`} />
            {analysing ? 'Analysing...' : 'Run AI Analysis'}
          </Button>
        </div>
      </div>

      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="account">
            Account
            {schedule.status === 'overdue' && !client.lost_at && (
              <Badge variant="destructive" className="ml-2 text-xs">Overdue</Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="communications">
            Communications
            {communications.length > 0 && (
              <Badge variant="secondary" className="ml-2 text-xs">
                {communications.length}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="invoices">
            Invoices
            {outstandingInvoices.length > 0 && (
              <Badge variant="destructive" className="ml-2 text-xs">
                {outstandingInvoices.length}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="contacts">
            Contacts
            {contacts.length > 0 && (
              <Badge variant="secondary" className="ml-2 text-xs">{contacts.length}</Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="ai-analysis">AI Analysis</TabsTrigger>
        </TabsList>

        {/* OVERVIEW TAB */}
        <TabsContent value="overview" className="space-y-4 mt-4">
          <div className="grid gap-4 md:grid-cols-2">
            {/* Score card */}
            <Card>
              <CardHeader>
                <CardTitle>Current Happiness</CardTitle>
                <CardDescription>Latest AI-generated score</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {latest_score ? (
                  <>
                    <ScoreBar score={latest_score.score} />
                    <div className="flex items-center gap-2">
                      <span className="text-sm text-muted-foreground">Churn Risk:</span>
                      <Badge
                        variant={
                          latest_score.churn_risk === 'low'
                            ? 'default'
                            : latest_score.churn_risk === 'medium'
                            ? 'secondary'
                            : 'destructive'
                        }
                        className="capitalize"
                      >
                        {latest_score.churn_risk}
                      </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      Last scored:{' '}
                      {new Date(latest_score.scored_at).toLocaleDateString('en-GB')}
                    </p>
                  </>
                ) : (
                  <div className="text-center py-6 text-muted-foreground">
                    <Brain className="h-8 w-8 mx-auto mb-2 opacity-30" />
                    <p className="text-sm">No score yet. Run AI Analysis to generate one.</p>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Key metrics */}
            <Card>
              <CardHeader>
                <CardTitle>Key Metrics</CardTitle>
                <CardDescription>Communication and invoice summary</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground flex items-center gap-1">
                    <MessageSquare className="h-3 w-3" />
                    Total Communications
                  </span>
                  <span className="font-medium">{communications.length}</span>
                </div>
                <Separator />
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground flex items-center gap-1">
                    <Receipt className="h-3 w-3" />
                    Outstanding Invoices
                  </span>
                  <span className="font-medium text-destructive">
                    {outstandingInvoices.length}
                  </span>
                </div>
                <Separator />
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">Total Outstanding</span>
                  <span className="font-medium text-destructive">
                    {formatCurrency(
                      outstandingInvoices.reduce((sum, i) => sum + i.amount_pence, 0)
                    )}
                  </span>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Score history mini chart */}
          {score_history.length > 1 && (
            <Card>
              <CardHeader>
                <CardTitle>Score History</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex items-end gap-2 h-24">
                  {score_history.slice(-10).map((s) => {
                    const pct = (s.score / 10) * 100
                    const color =
                      s.score >= 7
                        ? 'bg-green-500'
                        : s.score >= 4
                        ? 'bg-yellow-500'
                        : 'bg-red-500'
                    return (
                      <div key={s.id} className="flex-1 flex flex-col items-center gap-1">
                        <div className="w-full bg-muted rounded-t" style={{ height: `${pct}%` }}>
                          <div className={`w-full h-full ${color} rounded-t opacity-80`} />
                        </div>
                        <span className="text-xs text-muted-foreground">
                          {s.score.toFixed(0)}
                        </span>
                      </div>
                    )
                  })}
                </div>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* ACCOUNT TAB: contact schedule, history and notes */}
        <TabsContent value="account" className="space-y-4 mt-4">
          <Card>
            <CardHeader>
              <CardTitle>Contact schedule</CardTitle>
              <CardDescription>
                {client.is_enterprise ? 'Enterprise customer: ' : ''}
                contact {schedule.interval_months === 1 ? 'every month' : `every ${schedule.interval_months} months`}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 sm:grid-cols-3 text-sm">
                <div>
                  <p className="text-muted-foreground">Last contacted</p>
                  <p className="font-medium">{formatDay(schedule.last_contacted_at) ?? 'Never'}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">Next contact due</p>
                  <p className="font-medium">{client.lost_at ? 'Not scheduled (lost)' : formatDay(schedule.next_due_at)}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">Status</p>
                  {client.lost_at ? (
                    <Badge variant="outline">Lost</Badge>
                  ) : (
                    <Badge
                      variant={schedule.status === 'overdue' ? 'destructive' : schedule.status === 'due' ? 'secondary' : 'outline'}
                      className="capitalize"
                    >
                      {schedule.status === 'due' ? 'Due soon' : schedule.status}
                    </Badge>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Contact history</CardTitle>
              <CardDescription>Completed scheduled contacts</CardDescription>
            </CardHeader>
            <CardContent>
              {contact_logs.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  <CalendarCheck className="h-10 w-10 mx-auto mb-3 opacity-30" />
                  <p>No contacts recorded yet.</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {contact_logs.map((log) => (
                    <div key={log.id} className="border-l-2 border-primary pl-4">
                      <div className="flex items-start justify-between gap-2">
                        <div className="text-sm">
                          <span className="font-medium">{formatDay(log.contacted_at)}</span>
                          <span className="text-muted-foreground"> by {log.user?.name ?? 'a removed user'}</span>
                        </div>
                        {canDelete(log.user) && (
                          <Button variant="ghost" size="sm" onClick={() => deleteContactLog(log.id)} title="Remove">
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        )}
                      </div>
                      {log.communication && (
                        <Link
                          href={`/communications/${log.communication.id}`}
                          className="inline-flex items-center gap-1 text-sm text-primary hover:underline mt-1"
                        >
                          <Video className="h-3.5 w-3.5" />
                          {log.communication.subject ?? 'Fireflies call'}
                          {log.communication.occurred_at && ` (${formatDay(log.communication.occurred_at)})`}
                        </Link>
                      )}
                      {log.notes && <p className="text-sm mt-1 whitespace-pre-wrap">{log.notes}</p>}
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Notes</CardTitle>
              <CardDescription>Shared notes about this customer</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={addNote} className="space-y-2">
                <textarea
                  className="w-full border rounded-md px-3 py-2 text-sm bg-background resize-y"
                  rows={3}
                  placeholder="Add a note..."
                  value={noteBody}
                  onChange={(e) => setNoteBody(e.target.value)}
                />
                <div className="flex justify-end">
                  <Button type="submit" size="sm" disabled={savingNote || !noteBody.trim()}>
                    <StickyNote className="h-4 w-4 mr-2" />
                    {savingNote ? 'Saving...' : 'Add note'}
                  </Button>
                </div>
              </form>
              {notes.length === 0 ? (
                <p className="text-sm text-muted-foreground text-center py-4">No notes yet.</p>
              ) : (
                <div className="space-y-3">
                  {notes.map((note) => (
                    <div key={note.id} className="rounded-md border p-3">
                      <div className="flex items-start justify-between gap-2">
                        <p className="text-xs text-muted-foreground">
                          {note.user?.name ?? 'A removed user'} -{' '}
                          {new Date(note.created_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}
                        </p>
                        {canDelete(note.user) && (
                          <Button variant="ghost" size="sm" onClick={() => deleteNote(note.id)} title="Delete note">
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        )}
                      </div>
                      <p className="text-sm mt-1 whitespace-pre-wrap">{note.body}</p>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* COMMUNICATIONS TAB */}
        <TabsContent value="communications" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle>Communications</CardTitle>
              <CardDescription>All interactions from Freshdesk, Fireflies, and helpdesk</CardDescription>
            </CardHeader>
            <CardContent>
              {communications.length === 0 ? (
                <div className="text-center py-12 text-muted-foreground">
                  <MessageSquare className="h-12 w-12 mx-auto mb-4 opacity-30" />
                  <p>No communications recorded yet.</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {communications.map((comm) => (
                    <div key={comm.id} className="border rounded-lg p-4 space-y-2">
                      <div className="flex items-start justify-between">
                        <div className="flex items-center gap-2">
                          <Badge variant="outline" className="capitalize">
                            {sourceBadge(comm.source)}
                          </Badge>
                          {comm.subject && (
                            <span className="font-medium text-sm">{comm.subject}</span>
                          )}
                        </div>
                        <div className="flex items-center gap-2">
                          {comm.sentiment_score !== null && (
                            <Badge
                              variant={
                                comm.sentiment_score >= 0.5
                                  ? 'default'
                                  : comm.sentiment_score >= 0
                                  ? 'secondary'
                                  : 'destructive'
                              }
                            >
                              Sentiment: {comm.sentiment_score.toFixed(2)}
                            </Badge>
                          )}
                          <span className="text-xs text-muted-foreground">
                            {comm.occurred_at ? new Date(comm.occurred_at).toLocaleDateString('en-GB') : '—'}
                          </span>
                        </div>
                      </div>
                      <p className="text-sm text-muted-foreground line-clamp-3">{comm.body}</p>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* INVOICES TAB */}
        <TabsContent value="invoices" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle>Invoices</CardTitle>
              <CardDescription>Invoice history from FreeAgent</CardDescription>
            </CardHeader>
            <CardContent>
              {invoices.length === 0 ? (
                <div className="text-center py-12 text-muted-foreground">
                  <Receipt className="h-12 w-12 mx-auto mb-4 opacity-30" />
                  <p>No invoices recorded yet.</p>
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Invoice #</TableHead>
                      <TableHead>Amount</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Issued</TableHead>
                      <TableHead>Due</TableHead>
                      <TableHead>Paid</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {invoices.map((inv) => (
                      <TableRow key={inv.id}>
                        <TableCell className="font-mono">
                          {inv.view_url ? (
                            <a
                              href={inv.view_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="underline underline-offset-4 hover:text-primary"
                            >
                              {inv.invoice_number}
                            </a>
                          ) : (
                            inv.invoice_number
                          )}
                        </TableCell>
                        <TableCell>{formatCurrency(inv.amount_pence, inv.currency)}</TableCell>
                        <TableCell>
                          <Badge
                            variant={
                              inv.status === 'paid'
                                ? 'default'
                                : inv.status === 'overdue'
                                ? 'destructive'
                                : 'secondary'
                            }
                            className="capitalize"
                          >
                            {inv.status}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          {new Date(inv.issued_at).toLocaleDateString('en-GB')}
                        </TableCell>
                        <TableCell>{new Date(inv.due_at).toLocaleDateString('en-GB')}</TableCell>
                        <TableCell>
                          {inv.paid_at ? (
                            new Date(inv.paid_at).toLocaleDateString('en-GB')
                          ) : (
                            <span className="text-muted-foreground">—</span>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* CONTACTS TAB */}
        <TabsContent value="contacts" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle>Contacts</CardTitle>
              <CardDescription>People synced from CMP, with their latest happiness review if any</CardDescription>
            </CardHeader>
            <CardContent>
              {contacts.length === 0 ? (
                <div className="text-center py-12 text-muted-foreground">
                  <Users className="h-12 w-12 mx-auto mb-4 opacity-30" />
                  <p>No contacts synced yet. Run a full sync to pull contacts from CMP.</p>
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead>Email</TableHead>
                      <TableHead>Phone</TableHead>
                      <TableHead>Review</TableHead>
                      <TableHead>Review Date</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {contacts.map((contact) => (
                      <TableRow key={contact.id}>
                        <TableCell className="font-medium">
                          {contact.name ?? <span className="text-muted-foreground">—</span>}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {contact.email ?? '—'}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {contact.phone ?? '—'}
                        </TableCell>
                        <TableCell>
                          {contact.latest_review ? (
                            <div className="flex items-center gap-1.5">
                              <Star className="h-3.5 w-3.5 text-yellow-500 fill-yellow-500" />
                              <span className="font-medium text-sm">
                                {contact.latest_review.score}/7
                              </span>
                              <Badge
                                variant={
                                  (contact.latest_review.score ?? 0) >= 5 ? 'default'
                                  : (contact.latest_review.score ?? 0) >= 3 ? 'secondary'
                                  : 'destructive'
                                }
                                className="text-xs"
                              >
                                {(contact.latest_review.score ?? 0) >= 5 ? 'Positive'
                                  : (contact.latest_review.score ?? 0) >= 3 ? 'Neutral'
                                  : 'Negative'}
                              </Badge>
                            </div>
                          ) : (
                            <span className="text-xs text-muted-foreground">No review</span>
                          )}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {contact.latest_review?.occurred_at
                            ? new Date(contact.latest_review.occurred_at).toLocaleDateString('en-GB')
                            : '—'}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* AI ANALYSIS TAB */}
        <TabsContent value="ai-analysis" className="mt-4">
          {latest_score ? (
            <div className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>AI Assessment Summary</CardTitle>
                  <CardDescription>
                    Generated on{' '}
                    {new Date(latest_score.scored_at).toLocaleDateString('en-GB', {
                      weekday: 'long',
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                    })}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <p className="text-sm leading-relaxed">{latest_score.analysis_summary}</p>
                </CardContent>
              </Card>

              <div className="grid gap-4 md:grid-cols-2">
                {latest_score.key_concerns.length > 0 && (
                  <Card>
                    <CardHeader>
                      <CardTitle className="flex items-center gap-2 text-base">
                        <AlertTriangle className="h-4 w-4 text-destructive" />
                        Key Concerns
                      </CardTitle>
                    </CardHeader>
                    <CardContent>
                      <ul className="space-y-2">
                        {latest_score.key_concerns.map((concern, i) => (
                          <li key={i} className="flex items-start gap-2 text-sm">
                            <span className="mt-1 h-1.5 w-1.5 rounded-full bg-destructive shrink-0" />
                            {concern}
                          </li>
                        ))}
                      </ul>
                    </CardContent>
                  </Card>
                )}

                {latest_score.recommended_actions.length > 0 && (
                  <Card>
                    <CardHeader>
                      <CardTitle className="flex items-center gap-2 text-base">
                        <CheckCircle className="h-4 w-4 text-green-500" />
                        Recommended Actions
                      </CardTitle>
                    </CardHeader>
                    <CardContent>
                      <ul className="space-y-2">
                        {latest_score.recommended_actions.map((action, i) => (
                          <li key={i} className="flex items-start gap-2 text-sm">
                            <span className="mt-1 h-1.5 w-1.5 rounded-full bg-green-500 shrink-0" />
                            {action}
                          </li>
                        ))}
                      </ul>
                    </CardContent>
                  </Card>
                )}
              </div>
            </div>
          ) : (
            <Card>
              <CardContent className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                <Brain className="h-16 w-16 mb-4 opacity-30" />
                <p className="text-lg font-medium mb-2">No AI analysis yet</p>
                <p className="text-sm mb-6">
                  Click "Run AI Analysis" to generate a happiness score and recommendations.
                </p>
                <Button onClick={triggerAnalysis} disabled={analysing}>
                  <RefreshCw className={`h-4 w-4 mr-2 ${analysing ? 'animate-spin' : ''}`} />
                  {analysing ? 'Analysing...' : 'Run AI Analysis'}
                </Button>
              </CardContent>
            </Card>
          )}
        </TabsContent>
      </Tabs>

      <CompleteContactDialog
        client={completingContact ? { id: client.id, name: client.name } : null}
        calls={fireflies_calls}
        onOpenChange={setCompletingContact}
      />
    </AppLayout>
  )
}
