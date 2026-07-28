import React from 'react'
import { Head, Link, router, useForm } from '@inertiajs/react'
import AppLayout from '@/layouts/AppLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ArrowLeft, Sparkles, Users, Clock } from 'lucide-react'

interface Attendee {
  name: string
  email: string
}

interface Sentence {
  speaker: string
  text: string
}

interface Communication {
  id: number
  source: string
  subject: string | null
  body: string
  occurred_at: string | null
  sentiment_score: number | null
  tone_summary: string | null
  client: { id: number; name: string } | null
  attendees: Attendee[]
  sentences: Sentence[]
  duration: number | null
  summary: string | null
  action_items: string | null
}

interface Props {
  communication: Communication
}

const sourceLabel: Record<string, string> = {
  freshdesk: 'Support',
  fireflies: 'Call',
  onboarding_helpdesk: 'Onboarding',
  happiness_review: 'Review',
}

function scoreColour(score: number): string {
  if (score >= 7) return 'text-green-600 dark:text-green-400'
  if (score >= 4) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-red-600 dark:text-red-400'
}

function scoreBg(score: number): string {
  if (score >= 7) return 'bg-green-50 border-green-200 dark:bg-green-950 dark:border-green-800'
  if (score >= 4) return 'bg-yellow-50 border-yellow-200 dark:bg-yellow-950 dark:border-yellow-800'
  return 'bg-red-50 border-red-200 dark:bg-red-950 dark:border-red-800'
}

function formatDuration(seconds: number): string {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return s > 0 ? `${m}m ${s}s` : `${m}m`
}

// Group consecutive sentences by speaker so the transcript reads like a conversation
function groupSentences(sentences: Sentence[]): { speaker: string; lines: string[] }[] {
  const groups: { speaker: string; lines: string[] }[] = []
  for (const s of sentences) {
    const last = groups[groups.length - 1]
    if (last && last.speaker === s.speaker) {
      last.lines.push(s.text)
    } else {
      groups.push({ speaker: s.speaker, lines: [s.text] })
    }
  }
  return groups
}

// Cycle through a small set of colours for different speakers
const speakerColours = [
  'text-blue-700 dark:text-blue-400',
  'text-purple-700 dark:text-purple-400',
  'text-emerald-700 dark:text-emerald-400',
  'text-orange-700 dark:text-orange-400',
  'text-pink-700 dark:text-pink-400',
]

export default function CommunicationShow({ communication }: Props) {
  const { post, processing } = useForm()

  function analyse() {
    post(`/communications/${communication.id}/analyse`)
  }

  const isFireflies  = communication.source === 'fireflies'
  const groups       = isFireflies ? groupSentences(communication.sentences) : []
  const speakerIndex: Record<string, number> = {}
  let speakerCount   = 0

  function speakerColour(name: string): string {
    if (!(name in speakerIndex)) speakerIndex[name] = speakerCount++
    return speakerColours[speakerIndex[name] % speakerColours.length]
  }

  return (
    <AppLayout title={communication.subject ?? 'Communication'}>
      <Head title={communication.subject ?? 'Communication'} />

      {/* Back + header */}
      <div className="flex items-start gap-4 mb-6">
        <Button variant="ghost" size="sm" asChild className="mt-0.5">
          <Link href="/communications">
            <ArrowLeft className="h-4 w-4 mr-1" />
            Back
          </Link>
        </Button>
        <div className="flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <Badge variant="outline">{sourceLabel[communication.source] ?? communication.source}</Badge>
            {communication.client && (
              <Link
                href={`/clients/${communication.client.id}`}
                className="text-sm text-muted-foreground hover:underline"
              >
                {communication.client.name}
              </Link>
            )}
            {communication.occurred_at && (
              <span className="text-sm text-muted-foreground">
                {new Date(communication.occurred_at).toLocaleDateString('en-GB', {
                  day: 'numeric', month: 'long', year: 'numeric',
                })}
              </span>
            )}
            {communication.duration && (
              <span className="text-sm text-muted-foreground flex items-center gap-1">
                <Clock className="h-3 w-3" />
                {formatDuration(communication.duration)}
              </span>
            )}
          </div>
          <h1 className="text-xl font-semibold mt-1">
            {communication.subject ?? 'No subject'}
          </h1>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Main transcript / body */}
        <div className="lg:col-span-2 space-y-4">

          {/* Fireflies: AI summary + action items */}
          {isFireflies && communication.summary && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Summary</CardTitle>
              </CardHeader>
              <CardContent className="text-sm leading-relaxed whitespace-pre-wrap">
                {communication.summary}
                {communication.action_items && (
                  <div className="mt-3 pt-3 border-t">
                    <p className="font-medium mb-1">Action items</p>
                    <p className="whitespace-pre-wrap text-muted-foreground">
                      {communication.action_items}
                    </p>
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* Transcript or body */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">
                {isFireflies && groups.length > 0 ? 'Transcript' : 'Content'}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {isFireflies && groups.length > 0 ? (
                <div className="space-y-4 text-sm">
                  {groups.map((g, i) => (
                    <div key={i} className="flex gap-3">
                      <span className={`font-semibold whitespace-nowrap w-28 shrink-0 ${speakerColour(g.speaker)}`}>
                        {g.speaker}
                      </span>
                      <p className="leading-relaxed">{g.lines.join(' ')}</p>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm leading-relaxed whitespace-pre-wrap">{communication.body}</p>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-4">

          {/* Tone analysis */}
          <Card className={communication.sentiment_score != null ? `border ${scoreBg(communication.sentiment_score)}` : ''}>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="text-base">Tone Analysis</CardTitle>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={analyse}
                  disabled={processing}
                >
                  <Sparkles className="h-3 w-3 mr-1" />
                  {communication.sentiment_score != null ? 'Re-analyse' : 'Analyse'}
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {communication.sentiment_score != null ? (
                <div>
                  <div className={`text-4xl font-bold mb-2 ${scoreColour(communication.sentiment_score)}`}>
                    {communication.sentiment_score.toFixed(1)}
                    <span className="text-lg font-normal text-muted-foreground"> / 10</span>
                  </div>
                  {communication.tone_summary && (
                    <p className="text-sm leading-relaxed">{communication.tone_summary}</p>
                  )}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">
                  No analysis yet. Click Analyse to score this {sourceLabel[communication.source] ?? 'communication'} with AI.
                </p>
              )}
            </CardContent>
          </Card>

          {/* Attendees (Fireflies only) */}
          {isFireflies && communication.attendees.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base flex items-center gap-2">
                  <Users className="h-4 w-4" />
                  Attendees
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                {communication.attendees.map((a, i) => (
                  <div key={i}>
                    <p className="text-sm font-medium">{a.name || '—'}</p>
                    {a.email && (
                      <p className="text-xs text-muted-foreground">{a.email}</p>
                    )}
                  </div>
                ))}
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </AppLayout>
  )
}
