import React from 'react'
import { Head, router } from '@inertiajs/react'
import AppLayout from '@/layouts/AppLayout'
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
} from 'lucide-react'

interface Client {
  id: number
  name: string
  email: string
  phone: string | null
  company_name: string
  is_new_customer: boolean
  freshdesk_company_id: string | null
  freeagent_contact_id: string | null
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

interface Props {
  client: Client
  communications: Communication[]
  invoices: Invoice[]
  latest_score: HappinessScore | null
  score_history: HappinessScore[]
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
  email: '',
  phone: null,
  company_name: '',
  is_new_customer: false,
  freshdesk_company_id: null,
  freeagent_contact_id: null,
}

export default function ClientShow({
  client = emptyClient,
  communications = [],
  invoices = [],
  latest_score = null,
  score_history = [],
}: Props) {
  const [analysing, setAnalysing] = React.useState(false)

  function triggerAnalysis() {
    setAnalysing(true)
    router.post(
      `/clients/${client.id}/analyse`,
      {},
      {
        onFinish: () => setAnalysing(false),
      }
    )
  }

  const outstandingInvoices = invoices.filter((i) => !i.paid_at)

  return (
    <AppLayout title={client.name}>
      <Head title={client.name} />

      {/* Header */}
      <div className="flex items-start justify-between mb-6">
        <div>
          <h2 className="text-2xl font-bold">{client.name}</h2>
          <p className="text-muted-foreground">{client.company_name}</p>
          <div className="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
            <span className="flex items-center gap-1">
              <Mail className="h-3 w-3" />
              {client.email}
            </span>
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
          </div>
        </div>
        <Button onClick={triggerAnalysis} disabled={analysing}>
          <RefreshCw className={`h-4 w-4 mr-2 ${analysing ? 'animate-spin' : ''}`} />
          {analysing ? 'Analysing...' : 'Run AI Analysis'}
        </Button>
      </div>

      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
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
                            {new Date(comm.occurred_at).toLocaleDateString('en-GB')}
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
                        <TableCell className="font-mono">{inv.invoice_number}</TableCell>
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
    </AppLayout>
  )
}
