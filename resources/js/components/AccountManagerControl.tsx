import React from 'react'
import { router, usePage } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { UserPlus, UserMinus } from 'lucide-react'

export interface UserOption {
  id: number
  name: string
}

interface Props {
  clientId: number
  current: UserOption | null
  users: UserOption[]
}

interface PageProps {
  auth: { user: { id: number; role: string } | null }
  [key: string]: unknown
}

/**
 * Admins can assign any account manager; account managers can assign or
 * unassign themselves; viewers just see who manages the customer.
 */
export default function AccountManagerControl({ clientId, current, users }: Props) {
  const { auth } = usePage<PageProps>().props
  const me = auth.user
  const isAdmin = me?.role === 'admin'

  function assign(userId: number | null) {
    router.patch(
      `/clients/${clientId}/account-manager`,
      { account_manager_id: userId },
      { preserveScroll: true, preserveState: true }
    )
  }

  if (isAdmin) {
    return (
      <select
        className="border rounded-md px-2 py-1 text-sm bg-background max-w-[12rem]"
        value={current?.id ?? ''}
        onChange={(e) => assign(e.target.value ? Number(e.target.value) : null)}
      >
        <option value="">Unassigned</option>
        {users.map((u) => (
          <option key={u.id} value={u.id}>
            {u.name}
          </option>
        ))}
      </select>
    )
  }

  const isMine = current?.id === me?.id
  const canAssignSelf = me?.role === 'account_manager'

  return (
    <div className="flex items-center gap-2">
      <span className={current ? 'text-sm' : 'text-sm text-muted-foreground'}>
        {current?.name ?? 'Unassigned'}
      </span>
      {canAssignSelf && (isMine ? (
        <Button variant="ghost" size="sm" onClick={() => assign(null)} title="Unassign me">
          <UserMinus className="h-3.5 w-3.5" />
        </Button>
      ) : (
        <Button variant="ghost" size="sm" onClick={() => me && assign(me.id)} title="Assign to me">
          <UserPlus className="h-3.5 w-3.5 mr-1" />
          Assign to me
        </Button>
      ))}
    </div>
  )
}
