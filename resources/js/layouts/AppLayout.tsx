import React, { useEffect, useState } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import {
  LayoutDashboard,
  Users,
  MessageSquare,
  Receipt,
  Settings,
  Menu,
  X,
  Heart,
  LogOut,
  CheckCircle2,
  AlertCircle,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'

interface NavItem {
  label: string
  href: string
  icon: React.ComponentType<{ className?: string }>
  adminOnly?: boolean
}

const navItems: NavItem[] = [
  { label: 'Dashboard',      href: '/dashboard',      icon: LayoutDashboard },
  { label: 'Clients',        href: '/clients',         icon: Users },
  { label: 'Communications', href: '/communications',  icon: MessageSquare },
  { label: 'Invoices',       href: '/invoices',        icon: Receipt },
  { label: 'Settings',       href: '/settings',        icon: Settings, adminOnly: true },
]

interface AuthUser {
  id: number
  name: string
  email: string
  role: string
}

interface PageProps {
  auth: { user: AuthUser | null }
  flash: { success?: string; error?: string }
  [key: string]: unknown
}

interface AppLayoutProps {
  children: React.ReactNode
  title?: string
}

export default function AppLayout({ children, title }: AppLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [flash, setFlash] = useState<{ success?: string; error?: string }>({})
  const { url, props } = usePage<PageProps>()
  const { auth, flash: pageFlash } = props

  useEffect(() => {
    if (pageFlash?.success || pageFlash?.error) {
      setFlash(pageFlash)
      const timer = setTimeout(() => setFlash({}), 4000)
      return () => clearTimeout(timer)
    }
  }, [pageFlash?.success, pageFlash?.error])

  function logout() {
    router.post('/logout')
  }

  const isAdmin = auth.user?.role === 'admin'

  return (
    <div className="flex h-screen overflow-hidden bg-background">
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/50 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r bg-card transition-transform duration-300 lg:static lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        )}
      >
        <div className="flex h-16 items-center gap-2 px-6">
          <Heart className="h-6 w-6 text-primary" />
          <span className="font-semibold text-foreground">Customer Happiness</span>
        </div>

        <Separator />

        <nav className="flex-1 space-y-1 p-4">
          {navItems.map((item) => {
            if (item.adminOnly && !isAdmin) return null
            const Icon = item.icon
            const isActive = url.startsWith(item.href)
            return (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                )}
              >
                <Icon className="h-4 w-4" />
                {item.label}
              </Link>
            )
          })}
        </nav>

        <div className="p-4">
          <Separator className="mb-4" />
          {auth.user && (
            <div className="flex items-center justify-between">
              <div className="min-w-0">
                <p className="text-sm font-medium text-foreground truncate">{auth.user.name}</p>
                <p className="text-xs text-muted-foreground truncate">{auth.user.email}</p>
              </div>
              <Button
                variant="ghost"
                size="icon"
                className="shrink-0 text-muted-foreground hover:text-foreground"
                onClick={logout}
                title="Sign out"
              >
                <LogOut className="h-4 w-4" />
              </Button>
            </div>
          )}
        </div>
      </aside>

      {/* Main content */}
      <div className="flex flex-1 flex-col overflow-hidden">
        <header className="flex h-16 items-center gap-4 border-b bg-card px-6">
          <Button
            variant="ghost"
            size="icon"
            className="lg:hidden"
            onClick={() => setSidebarOpen(!sidebarOpen)}
          >
            {sidebarOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </Button>
          <h1 className="text-lg font-semibold">{title ?? 'Customer Happiness'}</h1>
        </header>

        {/* Flash messages */}
        {(flash.success || flash.error) && (
          <div
            className={cn(
              'flex items-center gap-3 px-6 py-3 text-sm font-medium',
              flash.success
                ? 'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200'
                : 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200'
            )}
          >
            {flash.success
              ? <CheckCircle2 className="h-4 w-4 shrink-0" />
              : <AlertCircle className="h-4 w-4 shrink-0" />}
            {flash.success ?? flash.error}
          </div>
        )}

        <main className="flex-1 overflow-auto p-6">{children}</main>
      </div>
    </div>
  )
}
