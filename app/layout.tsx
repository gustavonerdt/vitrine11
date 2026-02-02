import React from "react"
import type { Metadata, Viewport } from "next"
import { Inter, Sora } from "next/font/google"
import "./globals.css"

const inter = Inter({ 
  subsets: ["latin"],
  variable: "--font-inter",
  display: "swap",
})

const sora = Sora({ 
  subsets: ["latin"],
  variable: "--font-sora",
  display: "swap",
})

export const metadata: Metadata = {
  title: "Vitrine11 - E-commerce Premium",
  description: "Sua loja online completa com as melhores solucoes para vender mais",
  keywords: ["e-commerce", "loja online", "vitrine", "vendas", "perfumes"],
}

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#D97706",
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="pt-BR" className={`${inter.variable} ${sora.variable}`}>
      <body className={`${inter.className} antialiased`}>{children}</body>
    </html>
  )
}
