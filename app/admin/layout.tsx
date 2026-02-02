"use client"

import type React from "react"
import { useState, useEffect } from "react"
import Link from "next/link"
import { usePathname } from "next/navigation"

// Icones SVG inline para nao depender de bibliotecas externas
const Icons = {
  Dashboard: () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="3" width="7" height="7" />
      <rect x="14" y="3" width="7" height="7" />
      <rect x="14" y="14" width="7" height="7" />
      <rect x="3" y="14" width="7" height="7" />
    </svg>
  ),
  Home: () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
      <polyline points="9 22 9 12 15 12 15 22" />
    </svg>
  ),
  Logout: () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <polyline points="16 17 21 12 16 7" />
      <line x1="21" y1="12" x2="9" y2="12" />
    </svg>
  ),
  ChevronLeft: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="15 18 9 12 15 6" />
    </svg>
  ),
  ChevronRight: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="9 18 15 12 9 6" />
    </svg>
  ),
  Menu: () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="3" y1="12" x2="21" y2="12" />
      <line x1="3" y1="6" x2="21" y2="6" />
      <line x1="3" y1="18" x2="21" y2="18" />
    </svg>
  ),
  Close: () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="18" y1="6" x2="6" y2="18" />
      <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
  ),
  Bell: () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
      <path d="M13.73 21a2 2 0 0 1-3.46 0" />
    </svg>
  ),
}

const menuItems = [
  {
    title: "Principal",
    items: [
      { name: "Dashboard", href: "/admin", icon: Icons.Dashboard },
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

  // Fecha o menu mobile ao redimensionar
  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth > 1024) {
        setMobileMenuOpen(false)
      }
    }
    window.addEventListener("resize", handleResize)
    return () => window.removeEventListener("resize", handleResize)
  }, [])

  const isActive = (href: string) => {
    if (href === "/admin") {
      return pathname === "/admin"
    }
    return pathname.startsWith(href)
  }

  const sidebarClass = `admin-sidebar${sidebarExpanded ? "" : " collapsed"}${mobileMenuOpen ? " mobile-visible" : ""}`

  return (
    <div className="admin-layout">
      {/* Mobile Overlay */}
      {mobileMenuOpen && (
        <div
          className="mobile-overlay visible"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside className={sidebarClass}>
        {/* Header */}
        <div className="sidebar-header">
          <Link href="/admin" className="sidebar-logo">
            <div className="sidebar-logo-icon">V</div>
            <span className="sidebar-logo-text">Vitrine11</span>
          </Link>
        </div>

        {/* Navigation */}
        <nav className="sidebar-nav">
          {menuItems.map((section) => (
            <div key={section.title} className="sidebar-section">
              <span className="sidebar-section-title">{section.title}</span>
              <ul className="sidebar-menu">
                {section.items.map((item) => {
                  const Icon = item.icon
                  const active = isActive(item.href)
                  return (
                    <li key={item.name} className="sidebar-menu-item">
                      <Link
                        href={item.href}
                        className={`sidebar-menu-link${active ? " active" : ""}`}
                        title={!sidebarExpanded ? item.name : undefined}
                      >
                        <span className="sidebar-menu-icon">
                          <Icon />
                        </span>
                        <span className="sidebar-menu-text">{item.name}</span>
                      </Link>
                    </li>
                  )
                })}
              </ul>
            </div>
          ))}

          {/* Configuracoes e Sair */}
          <div className="sidebar-footer">
            <ul className="sidebar-menu">
              <li className="sidebar-menu-item">
                <Link
                  href="/"
                  className="sidebar-menu-link"
                  title={!sidebarExpanded ? "Voltar ao Site" : undefined}
                >
                  <span className="sidebar-menu-icon">
                    <Icons.Home />
                  </span>
                  <span className="sidebar-menu-text">Voltar ao Site</span>
                </Link>
              </li>
              <li className="sidebar-menu-item">
                <button
                  className="sidebar-menu-link logout"
                  title={!sidebarExpanded ? "Sair" : undefined}
                  style={{ width: "100%", textAlign: "left" }}
                >
                  <span className="sidebar-menu-icon">
                    <Icons.Logout />
                  </span>
                  <span className="sidebar-menu-text">Sair</span>
                </button>
              </li>
            </ul>
          </div>
        </nav>

        {/* Toggle Button */}
        <button
          onClick={() => setSidebarExpanded(!sidebarExpanded)}
          className="sidebar-toggle"
          aria-label={sidebarExpanded ? "Recolher menu" : "Expandir menu"}
        >
          {sidebarExpanded ? <Icons.ChevronLeft /> : <Icons.ChevronRight />}
        </button>
      </aside>

      {/* Main Content */}
      <main className="admin-main">
        {/* Top Header */}
        <header className="admin-header">
          <div className="admin-header-left">
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="mobile-menu-btn"
              aria-label={mobileMenuOpen ? "Fechar menu" : "Abrir menu"}
            >
              {mobileMenuOpen ? <Icons.Close /> : <Icons.Menu />}
            </button>
            <nav className="breadcrumb">
              <Link href="/admin">Admin</Link>
              <span className="breadcrumb-separator">/</span>
              <span className="breadcrumb-current">Dashboard</span>
            </nav>
          </div>

          <div className="admin-header-right">
            <button className="notification-btn" aria-label="Notificacoes">
              <Icons.Bell />
              <span className="notification-badge">3</span>
            </button>
            <div className="user-menu">
              <div className="user-avatar">A</div>
              <div className="user-info">
                <span className="user-name">Admin</span>
                <span className="user-role">Administrador</span>
              </div>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <div className="admin-content">{children}</div>
      </main>
    </div>
  )
}
