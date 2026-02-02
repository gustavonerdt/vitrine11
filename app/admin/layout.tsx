"use client"

import type React from "react"
import { useState } from "react"
import Link from "next/link"
import { usePathname } from "next/navigation"
import {
  LayoutDashboard,
  Package,
  ShoppingCart,
  Users,
  Settings,
  ChevronLeft,
  ChevronRight,
  LogOut,
  Menu,
  X,
  BarChart3,
  Tags,
  FileText,
  Bell,
  ImageIcon,
  Percent,
  Truck,
  CreditCard,
  MessageSquare,
  Star,
  Home,
} from "lucide-react"

const menuItems = [
  {
    title: "Principal",
    items: [
      { name: "Dashboard", href: "/admin", icon: LayoutDashboard },
      { name: "Estatisticas", href: "/admin/stats", icon: BarChart3 },
    ],
  },
  {
    title: "Catalogo",
    items: [
      { name: "Produtos", href: "/admin/products", icon: Package },
      { name: "Categorias", href: "/admin/categories", icon: Tags },
      { name: "Marcas", href: "/admin/brands", icon: Star },
      { name: "Banners", href: "/admin/banners", icon: ImageIcon },
    ],
  },
  {
    title: "Vendas",
    items: [
      { name: "Pedidos", href: "/admin/orders", icon: ShoppingCart },
      { name: "Cupons", href: "/admin/coupons", icon: Percent },
      { name: "Frete", href: "/admin/shipping", icon: Truck },
      { name: "Pagamentos", href: "/admin/payments", icon: CreditCard },
    ],
  },
  {
    title: "Clientes",
    items: [
      { name: "Usuarios", href: "/admin/users", icon: Users },
      { name: "Mensagens", href: "/admin/messages", icon: MessageSquare },
      { name: "Avaliacoes", href: "/admin/reviews", icon: Star },
    ],
  },
  {
    title: "Sistema",
    items: [
      { name: "Configuracoes", href: "/admin/settings", icon: Settings },
      { name: "Relatorios", href: "/admin/reports", icon: FileText },
      { name: "Notificacoes", href: "/admin/notifications", icon: Bell },
    ],
  },
]

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const [sidebarExpanded, setSidebarExpanded] = useState(true)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const pathname = usePathname()

  const isActive = (href: string) => {
    if (href === "/admin") {
      return pathname === "/admin"
    }
    return pathname.startsWith(href)
  }

  return (
    <div className="min-h-screen bg-zinc-50">
      {/* Mobile Overlay */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed top-0 left-0 h-full bg-white border-r border-zinc-200 z-50 transition-all duration-300 ease-in-out shadow-sm
          ${sidebarExpanded ? "w-64" : "w-[72px]"}
          ${mobileMenuOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0"}
        `}
      >
        {/* Header */}
        <div className="h-16 flex items-center justify-center border-b border-zinc-200 bg-gradient-to-r from-amber-50 to-transparent px-4">
          <Link href="/admin" className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-amber-500/20">
              V
            </div>
            {sidebarExpanded && (
              <span className="text-lg font-bold bg-gradient-to-r from-amber-600 to-amber-500 bg-clip-text text-transparent">
                Vitrine11
              </span>
            )}
          </Link>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto py-4 pb-20">
          {menuItems.map((section) => (
            <div key={section.title} className="mb-2">
              {sidebarExpanded && (
                <span className="px-5 py-2 text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                  {section.title}
                </span>
              )}
              <ul className="mt-1 space-y-1 px-2">
                {section.items.map((item) => {
                  const Icon = item.icon
                  const active = isActive(item.href)
                  return (
                    <li key={item.name}>
                      <Link
                        href={item.href}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200
                          ${sidebarExpanded ? "" : "justify-center"}
                          ${
                            active
                              ? "bg-gradient-to-r from-amber-500 to-amber-400 text-black font-semibold shadow-lg shadow-amber-500/20"
                              : "text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"
                          }
                        `}
                        title={!sidebarExpanded ? item.name : undefined}
                      >
                        <Icon className="w-5 h-5 flex-shrink-0" />
                        {sidebarExpanded && <span className="text-sm">{item.name}</span>}
                      </Link>
                    </li>
                  )
                })}
              </ul>
            </div>
          ))}

          {/* Logout */}
          <div className="mt-4 px-2 border-t border-zinc-200 pt-4">
            <Link
              href="/"
              className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 transition-all duration-200
                ${sidebarExpanded ? "" : "justify-center"}
              `}
              title={!sidebarExpanded ? "Voltar ao Site" : undefined}
            >
              <Home className="w-5 h-5 flex-shrink-0" />
              {sidebarExpanded && <span className="text-sm">Voltar ao Site</span>}
            </Link>
            <button
              className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-red-600 hover:bg-red-50 transition-all duration-200
                ${sidebarExpanded ? "" : "justify-center"}
              `}
              title={!sidebarExpanded ? "Sair" : undefined}
            >
              <LogOut className="w-5 h-5 flex-shrink-0" />
              {sidebarExpanded && <span className="text-sm">Sair</span>}
            </button>
          </div>
        </nav>

        {/* Toggle Button */}
        <button
          onClick={() => setSidebarExpanded(!sidebarExpanded)}
          className={`absolute bottom-4 bg-zinc-100 border border-zinc-200 rounded-full w-8 h-8 flex items-center justify-center text-zinc-600 hover:bg-amber-500 hover:text-black hover:border-amber-500 transition-all duration-200 shadow-sm
            ${sidebarExpanded ? "right-4" : "left-1/2 -translate-x-1/2"}
          `}
        >
          {sidebarExpanded ? <ChevronLeft className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
        </button>
      </aside>

      {/* Main Content */}
      <main
        className={`min-h-screen transition-all duration-300 ease-in-out
          ${sidebarExpanded ? "lg:ml-64" : "lg:ml-[72px]"}
        `}
      >
        {/* Top Header */}
        <header className="h-16 bg-white border-b border-zinc-200 sticky top-0 z-30 px-4 lg:px-6 flex items-center justify-between shadow-sm">
          <div className="flex items-center gap-4">
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="lg:hidden w-10 h-10 flex items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 hover:bg-amber-500 hover:text-black transition-all"
            >
              {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
            <nav className="hidden md:flex items-center gap-2 text-sm text-zinc-500">
              <Link href="/admin" className="hover:text-amber-600 transition-colors">
                Admin
              </Link>
              <span>/</span>
              <span className="text-zinc-900 font-medium">Dashboard</span>
            </nav>
          </div>

          <div className="flex items-center gap-3">
            <button className="w-10 h-10 flex items-center justify-center rounded-full bg-zinc-100 text-zinc-600 hover:bg-amber-500 hover:text-black transition-all relative">
              <Bell className="w-5 h-5" />
              <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                3
              </span>
            </button>
            <div className="flex items-center gap-3 px-3 py-2 bg-zinc-100 rounded-full">
              <div className="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-black font-bold text-sm">
                A
              </div>
              <div className="hidden sm:block">
                <div className="text-sm font-semibold text-zinc-900">Admin</div>
                <div className="text-xs text-zinc-500">Administrador</div>
              </div>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <div className="p-4 lg:p-6">{children}</div>
      </main>
    </div>
  )
}
