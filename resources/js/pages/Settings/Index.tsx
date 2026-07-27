import React, { useState } from 'react'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import AppLayout from '@/layouts/AppLayout'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  CheckCircle2, XCircle, Users, Plug, RefreshCw, Trash2, Mail, Clock,
} from 'lucide-react'

interface User {
  id: number
  name: string
  email: string
  role: string
  created_at: string
}

interface PendingInvitation {
  id: number
  email: string
  role: string
  invited_by: string
  expires_at: string
}

interface Integration {
  name: string
  key: string
  configured: boolean
}

interface Props {
  users: User[]
  pending_invitations: PendingInvitation[]
  integrations: Integration[]
  last_synced_at: string | null
  current_user_id: number
}

export default function SettingsIndex({
  users,
  pending_invitations,
  integrations,
  last_synced_at,
  current_user_id,
}: Props) {
  const [confirmRemove, setConfirmRemove] = useState<number | null>(null)
  const [confirmCancel, setConfirmCancel] = useState<number | null>(null)
  const [syncing, setSyncing] = useState(false)

  const inviteForm = useForm({ email: '', role: 'viewer' })

  function sendInvite(e: React.FormEvent) {
    e.preventDefault()
    inviteForm.post('/invitations', {
      onSuccess: () => inviteForm.reset(),
    })
  }

  function removeUser(id: number) {
    router.delete(`/settings/users/${id}`, {
      onSuccess: () => setConfirmRemove(null),
    })
  }

  function cancelInvitation(id: number) {
    router.delete(`/invitations/${id}`, {
      onSuccess: () => setConfirmCancel(null),
    })
  }

  function triggerSync() {
    setSyncing(true)
    router.post('/sync', {}, {
      onFinish: () => setSyncing(false),
    })
  }

  function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString('en-GB', {
      day: 'numeric', month: 'short', year: 'numeric',
    })
  }

  function timeAgo(iso: string) {
    const diff = Date.now() - new Date(iso).getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 60) return `${mins} minute${mins !== 1 ? 's' : ''} ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24) return `${hrs} hour${hrs !== 1 ? 's' : ''} ago`
    const days = Math.floor(hrs / 24)
    return `${days} day${days !== 1 ? 's' : ''} ago`
  }

  return (
    <AppLayout title="Settings">
      <Head title="Settings" />

      <Tabs defaultValue="users">
        <TabsList className="mb-6">
          <TabsTrigger value="users" className="gap-2">
            <Users className="h-4 w-4" />
            Users
          </TabsTrigger>
          <TabsTrigger value="integrations" className="gap-2">
            <Plug className="h-4 w-4" />
            Integrations
          </TabsTrigger>
          <TabsTrigger value="sync" className="gap-2">
            <RefreshCw className="h-4 w-4" />
            Sync
          </TabsTrigger>
        </TabsList>

        {/* ── Users ── */}
        <TabsContent value="users" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Team members</CardTitle>
              <CardDescription>{users.length} user{users.length !== 1 ? 's' : ''}</CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Joined</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {users.map((u) => (
                    <TableRow key={u.id}>
                      <TableCell className="font-medium">
                        {u.name}
                        {u.id === current_user_id && (
                          <span className="ml-2 text-xs text-muted-foreground">(you)</span>
                        )}
                      </TableCell>
                      <TableCell>{u.email}</TableCell>
                      <TableCell>
                        <Badge variant={u.role === 'admin' ? 'default' : 'secondary'}>
                          {u.role}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground text-sm">
                        {formatDate(u.created_at)}
                      </TableCell>
                      <TableCell className="text-right">
                        {u.id !== current_user_id && (
                          confirmRemove === u.id ? (
                            <div className="flex items-center justify-end gap-2">
                              <span className="text-sm text-muted-foreground">Remove?</span>
                              <Button size="sm" variant="destructive" onClick={() => removeUser(u.id)}>
                                Yes
                              </Button>
                              <Button size="sm" variant="ghost" onClick={() => setConfirmRemove(null)}>
                                No
                              </Button>
                            </div>
                          ) : (
                            <Button
                              size="sm"
                              variant="ghost"
                              className="text-destructive hover:text-destructive"
                              onClick={() => setConfirmRemove(u.id)}
                            >
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          )
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Invite someone</CardTitle>
              <CardDescription>They'll receive an email with a link to set up their account.</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={sendInvite} className="flex items-end gap-3">
                <div className="flex-1 space-y-2">
                  <Label htmlFor="invite-email">Email address</Label>
                  <Input
                    id="invite-email"
                    type="email"
                    placeholder="name@example.com"
                    value={inviteForm.data.email}
                    onChange={(e) => inviteForm.setData('email', e.target.value)}
                  />
                  {inviteForm.errors.email && (
                    <p className="text-sm text-destructive">{inviteForm.errors.email}</p>
                  )}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="invite-role">Role</Label>
                  <select
                    id="invite-role"
                    value={inviteForm.data.role}
                    onChange={(e) => inviteForm.setData('role', e.target.value)}
                    className="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  >
                    <option value="viewer">Viewer</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
                <Button type="submit" disabled={inviteForm.processing} className="gap-2">
                  <Mail className="h-4 w-4" />
                  Send invite
                </Button>
              </form>
            </CardContent>
          </Card>

          {pending_invitations.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>Pending invitations</CardTitle>
                <CardDescription>{pending_invitations.length} awaiting acceptance</CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Email</TableHead>
                      <TableHead>Role</TableHead>
                      <TableHead>Invited by</TableHead>
                      <TableHead>Expires</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {pending_invitations.map((inv) => (
                      <TableRow key={inv.id}>
                        <TableCell>{inv.email}</TableCell>
                        <TableCell>
                          <Badge variant={inv.role === 'admin' ? 'default' : 'secondary'}>
                            {inv.role}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-muted-foreground">{inv.invited_by}</TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {formatDate(inv.expires_at)}
                        </TableCell>
                        <TableCell className="text-right">
                          {confirmCancel === inv.id ? (
                            <div className="flex items-center justify-end gap-2">
                              <span className="text-sm text-muted-foreground">Cancel?</span>
                              <Button size="sm" variant="destructive" onClick={() => cancelInvitation(inv.id)}>
                                Yes
                              </Button>
                              <Button size="sm" variant="ghost" onClick={() => setConfirmCancel(null)}>
                                No
                              </Button>
                            </div>
                          ) : (
                            <Button
                              size="sm"
                              variant="ghost"
                              className="text-destructive hover:text-destructive"
                              onClick={() => setConfirmCancel(inv.id)}
                            >
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* ── Integrations ── */}
        <TabsContent value="integrations">
          <Card>
            <CardHeader>
              <CardTitle>Integration status</CardTitle>
              <CardDescription>
                API keys are configured via environment variables in Laravel Cloud.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="divide-y">
                {integrations.map((integration) => (
                  <div key={integration.key} className="flex items-center justify-between py-4">
                    <span className="font-medium">{integration.name}</span>
                    {integration.configured ? (
                      <div className="flex items-center gap-2 text-sm text-green-600">
                        <CheckCircle2 className="h-4 w-4" />
                        Configured
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <XCircle className="h-4 w-4" />
                        Not configured
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* ── Sync ── */}
        <TabsContent value="sync">
          <Card>
            <CardHeader>
              <CardTitle>Data sync</CardTitle>
              <CardDescription>
                Syncs run automatically at 2am daily. You can also trigger a manual sync here.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center gap-3 text-sm">
                <Clock className="h-4 w-4 text-muted-foreground" />
                {last_synced_at ? (
                  <span>
                    Last synced <span className="font-medium">{timeAgo(last_synced_at)}</span>
                    <span className="text-muted-foreground ml-1">({formatDate(last_synced_at)})</span>
                  </span>
                ) : (
                  <span className="text-muted-foreground">Never synced</span>
                )}
              </div>

              <Button onClick={triggerSync} disabled={syncing} className="gap-2">
                <RefreshCw className={`h-4 w-4 ${syncing ? 'animate-spin' : ''}`} />
                {syncing ? 'Dispatching…' : 'Run sync now'}
              </Button>

              <p className="text-xs text-muted-foreground">
                Sync dispatches background jobs for CMP, Freshdesk, Fireflies, FreeAgent, and Onboarding Helpdesk.
                Jobs run in the queue — allow a few minutes for data to appear.
              </p>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </AppLayout>
  )
}
