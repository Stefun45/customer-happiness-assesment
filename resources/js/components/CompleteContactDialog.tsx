import React, { useEffect, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export interface FirefliesCall {
  id: number
  subject: string
  occurred_at: string | null
}

interface Props {
  client: { id: number; name: string } | null
  // Pass the client's calls when already loaded; otherwise they are fetched
  calls?: FirefliesCall[]
  onOpenChange: (open: boolean) => void
}

// Calls this close to the contact date are offered for linking
const CALL_WINDOW_DAYS = 30
// The nearest call within this many days is pre-selected
const AUTO_SELECT_DAYS = 7
const DAY_MS = 24 * 60 * 60 * 1000

function todayIso() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function formatCallDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Unknown date'
}

export default function CompleteContactDialog({ client, calls, onOpenChange }: Props) {
  const [contactedAt, setContactedAt] = useState(todayIso())
  const [callId, setCallId] = useState<string>('none')
  const [notes, setNotes] = useState('')
  const [fetchedCalls, setFetchedCalls] = useState<FirefliesCall[] | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  // Memoised so the auto-select below only re-runs when the calls or date change
  const allCalls = useMemo(() => calls ?? fetchedCalls ?? [], [calls, fetchedCalls])

  // Reset whenever the dialog opens for a client
  useEffect(() => {
    if (!client) return
    setContactedAt(todayIso())
    setNotes('')
    setErrors({})
    setFetchedCalls(null)

    if (!calls) {
      fetch(`/clients/${client.id}/fireflies-calls`, { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : []))
        .then(setFetchedCalls)
        .catch(() => setFetchedCalls([]))
    }
  }, [client?.id])

  // Calls near the chosen date, closest first
  const nearbyCalls = useMemo(() => {
    const target = new Date(contactedAt).getTime()
    return allCalls
      .filter((c) => c.occurred_at)
      .map((c) => ({ ...c, distance: Math.abs(new Date(c.occurred_at!).getTime() - target) }))
      .filter((c) => c.distance <= CALL_WINDOW_DAYS * DAY_MS)
      .sort((a, b) => a.distance - b.distance)
  }, [allCalls, contactedAt])

  // Pre-select the nearest call, or none if nothing is close
  useEffect(() => {
    const nearest = nearbyCalls[0]
    setCallId(nearest && nearest.distance <= AUTO_SELECT_DAYS * DAY_MS ? String(nearest.id) : 'none')
  }, [nearbyCalls])

  function submit(e: React.FormEvent) {
    e.preventDefault()
    if (!client) return
    setSubmitting(true)
    router.post(
      `/clients/${client.id}/contacts`,
      {
        contacted_at: contactedAt,
        communication_id: callId === 'none' ? null : Number(callId),
        notes: notes || null,
      },
      {
        preserveScroll: true,
        onSuccess: () => onOpenChange(false),
        onError: (errs) => setErrors(errs),
        onFinish: () => setSubmitting(false),
      }
    )
  }

  const loadingCalls = !calls && fetchedCalls === null

  return (
    <Dialog open={client !== null} onOpenChange={onOpenChange}>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>Mark contact complete</DialogTitle>
            <DialogDescription>{client?.name}</DialogDescription>
          </DialogHeader>

          <div className="space-y-2">
            <Label htmlFor="contacted_at">Date of contact</Label>
            <Input
              id="contacted_at"
              type="date"
              max={todayIso()}
              value={contactedAt}
              onChange={(e) => setContactedAt(e.target.value)}
              required
            />
            {errors.contacted_at && <p className="text-sm text-destructive">{errors.contacted_at}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="communication_id">Fireflies call</Label>
            <select
              id="communication_id"
              className="w-full border rounded-md px-3 py-2 text-sm bg-background"
              value={callId}
              onChange={(e) => setCallId(e.target.value)}
              disabled={loadingCalls}
            >
              <option value="none">{loadingCalls ? 'Loading calls...' : 'No call recorded'}</option>
              {nearbyCalls.map((c) => (
                <option key={c.id} value={c.id}>
                  {formatCallDate(c.occurred_at)} - {c.subject}
                </option>
              ))}
            </select>
            {!loadingCalls && nearbyCalls.length === 0 && (
              <p className="text-xs text-muted-foreground">
                No Fireflies calls within {CALL_WINDOW_DAYS} days of this date.
              </p>
            )}
            {errors.communication_id && <p className="text-sm text-destructive">{errors.communication_id}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="notes">Notes</Label>
            <textarea
              id="notes"
              className="w-full border rounded-md px-3 py-2 text-sm bg-background resize-y"
              rows={4}
              placeholder="What was discussed, follow-ups, concerns..."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
            {errors.notes && <p className="text-sm text-destructive">{errors.notes}</p>}
          </div>

          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving...' : 'Mark complete'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
