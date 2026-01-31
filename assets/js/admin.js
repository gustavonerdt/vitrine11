class AdminPanel {
  constructor() {
    this.init()
  }

  async init() {
    await this.checkAdminSession()
    this.setupEventListeners()
    this.loadDashboard()
  }

  async checkAdminSession() {
    try {
      const response = await fetch("api/check-session.php")
      const data = await response.json()

      if (!data.authenticated || !data.is_admin) {
        window.location.href = "admin/login.html"
        return
      }

      document.getElementById("adminEmail").textContent = data.email
    } catch (error) {
      window.location.href = "admin/login.html"
    }
  }

  setupEventListeners() {
    // Menu items
    document.querySelectorAll(".menu-item").forEach((item) => {
      item.addEventListener("click", (e) => {
        e.preventDefault()
        this.switchPage(item.dataset.page)
      })
    })

    // Product form
    document.getElementById("productForm")?.addEventListener("submit", (e) => this.handleProductSubmit(e))
    document.getElementById("brandForm")?.addEventListener("submit", (e) => this.handleBrandSubmit(e))
    document.getElementById("magicLinkForm")?.addEventListener("submit", (e) => this.handleMagicLinkSubmit(e))

    // Modal close buttons
    document.querySelectorAll(".modal-close").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.target.closest(".modal").style.display = "none"
      })
    })
  }

  switchPage(page) {
    // Hide all pages
    document.querySelectorAll(".page-content").forEach((p) => (p.style.display = "none"))

    // Show selected page
    const pageEl = document.getElementById(`${page}-page`)
    if (pageEl) {
      pageEl.style.display = "block"
    }

    // Update menu active state
    document.querySelectorAll(".menu-item").forEach((m) => m.classList.remove("active"))
    document.querySelector(`[data-page="${page}"]`).classList.add("active")

    // Update title
    const titles = {
      dashboard: "Dashboard",
      produtos: "Gerenciar Produtos",
      marcas: "Gerenciar Marcas",
      usuarios: "Gerenciar Usuários",
      "magic-links": "Magic Links",
      logs: "Logs de Atividade",
    }
    document.getElementById("pageTitle").textContent = titles[page] || "Página"

    // Load page data
    if (page === "dashboard") this.loadDashboard()
    else if (page === "produtos") this.loadProducts()
    else if (page === "marcas") this.loadBrands()
    else if (page === "usuarios") this.loadUsers()
    else if (page === "magic-links") this.loadMagicLinks()
    else if (page === "logs") this.loadLogs()
  }

  async loadDashboard() {
    try {
      const [users, products, brands] = await Promise.all([
        fetch("api/admin-users.php?action=list").then((r) => r.json()),
        fetch("api/admin-products.php?action=list").then((r) => r.json()),
        fetch("api/admin-brands.php?action=list").then((r) => r.json()),
      ])

      document.getElementById("totalUsers").textContent = users.users?.length || 0
      document.getElementById("totalProducts").textContent = products.products?.length || 0
      document.getElementById("totalBrands").textContent = brands.brands?.length || 0
      document.getElementById("vipUsers").textContent = users.users?.filter((u) => u.is_vip).length || 0
    } catch (error) {
      console.error("Erro ao carregar dashboard:", error)
    }
  }

  async loadProducts() {
    try {
      const response = await fetch("api/admin-products.php?action=list")
      const data = await response.json()

      const tbody = document.getElementById("produtosTableBody")
      tbody.innerHTML = data.products
        .map(
          (p) => `
                <tr>
                    <td>${p.name}</td>
                    <td>${p.brand_name}</td>
                    <td>R$ ${p.price.toFixed(2)}</td>
                    <td><span class="status-badge status-${p.status}">${p.status}</span></td>
                    <td>
                        <button class="btn-small" onclick="admin.editProduct(${p.id})">Editar</button>
                        <button class="btn-small btn-danger" onclick="admin.deleteProduct(${p.id})">Deletar</button>
                    </td>
                </tr>
            `,
        )
        .join("")

      // Carregar marcas para select
      const brandsRes = await fetch("api/get-brands.php")
      const brandsData = await brandsRes.json()
      const brandSelect = document.getElementById("productBrandSelect")
      brandSelect.innerHTML = brandsData.brands.map((b) => `<option value="${b.id}">${b.name}</option>`).join("")
    } catch (error) {
      console.error("Erro ao carregar produtos:", error)
    }
  }

  async loadBrands() {
    try {
      const response = await fetch("api/admin-brands.php?action=list")
      const data = await response.json()

      const tbody = document.getElementById("marcasTableBody")
      tbody.innerHTML = data.brands
        .map(
          (b) => `
                <tr>
                    <td>${b.name}</td>
                    <td><span class="status-badge status-${b.status}">${b.status}</span></td>
                    <td>
                        <button class="btn-small" onclick="admin.editBrand(${b.id})">Editar</button>
                        <button class="btn-small btn-danger" onclick="admin.deleteBrand(${b.id})">Deletar</button>
                    </td>
                </tr>
            `,
        )
        .join("")
    } catch (error) {
      console.error("Erro ao carregar marcas:", error)
    }
  }

  async loadUsers() {
    try {
      const response = await fetch("api/admin-users.php?action=list")
      const data = await response.json()

      const tbody = document.getElementById("usuariosTableBody")
      tbody.innerHTML = data.users
        .map(
          (u) => `
                <tr>
                    <td>${u.name}</td>
                    <td>${u.email}</td>
                    <td><span class="status-badge status-${u.status}">${u.status}</span></td>
                    <td>${u.is_vip ? "✓ VIP" : "—"}</td>
                    <td>${new Date(u.created_at).toLocaleDateString("pt-BR")}</td>
                    <td>
                        <button class="btn-small" onclick="admin.toggleVip(${u.id})">Toggle VIP</button>
                        <button class="btn-small" onclick="admin.toggleUserStatus(${u.id})">Mudar Status</button>
                    </td>
                </tr>
            `,
        )
        .join("")
    } catch (error) {
      console.error("Erro ao carregar usuários:", error)
    }
  }

  async loadMagicLinks() {
    try {
      const response = await fetch("api/admin-magic-links.php?action=list")
      const data = await response.json()

      const tbody = document.getElementById("magicLinksTableBody")
      tbody.innerHTML = data.links
        .map(
          (link) => `
                <tr>
                    <td>${link.target_email}</td>
                    <td style="word-break: break-all; font-size: 11px;">${link.token}</td>
                    <td>${link.is_used ? "✓ Usado" : "⏳ Pendente"}</td>
                    <td>${new Date(link.created_at).toLocaleString("pt-BR")}</td>
                    <td>${new Date(link.expires_at).toLocaleString("pt-BR")}</td>
                </tr>
            `,
        )
        .join("")
    } catch (error) {
      console.error("Erro ao carregar magic links:", error)
    }
  }

  async loadLogs() {
    try {
      const response = await fetch("api/admin-logs.php?limit=100")
      const data = await response.json()

      const tbody = document.getElementById("logsTableBody")
      tbody.innerHTML = data.logs
        .map(
          (log) => `
                <tr>
                    <td>${log.action}</td>
                    <td>${log.user_id || "—"}</td>
                    <td>${log.ip_address}</td>
                    <td>${new Date(log.created_at).toLocaleString("pt-BR")}</td>
                    <td style="font-size: 11px;">${JSON.stringify(log.details).substring(0, 100)}...</td>
                </tr>
            `,
        )
        .join("")
    } catch (error) {
      console.error("Erro ao carregar logs:", error)
    }
  }

  openProductForm() {
    document.getElementById("productForm").reset()
    document.getElementById("productFormModal").style.display = "flex"
  }

  openBrandForm() {
    document.getElementById("brandForm").reset()
    document.getElementById("brandFormModal").style.display = "flex"
  }

  async handleProductSubmit(e) {
    e.preventDefault()

    const formData = new FormData(e.target)
    formData.append("action", "create")

    try {
      const response = await fetch("api/admin-products.php?action=create", {
        method: "POST",
        body: formData,
      })
      const data = await response.json()

      if (data.success) {
        alert(data.message)
        document.getElementById("productFormModal").style.display = "none"
        this.loadProducts()
      } else {
        alert(data.message)
      }
    } catch (error) {
      alert("Erro ao salvar produto")
    }
  }

  async handleBrandSubmit(e) {
    e.preventDefault()

    const formData = new FormData(e.target)

    try {
      const response = await fetch("api/admin-brands.php?action=create", {
        method: "POST",
        body: formData,
      })
      const data = await response.json()

      if (data.success) {
        alert(data.message)
        document.getElementById("brandFormModal").style.display = "none"
        this.loadBrands()
      } else {
        alert(data.message)
      }
    } catch (error) {
      alert("Erro ao salvar marca")
    }
  }

  async handleMagicLinkSubmit(e) {
    e.preventDefault()

    const email = document.getElementById("magicLinkEmail").value
    const formData = new FormData()
    formData.append("email", email)

    try {
      const response = await fetch("api/admin-magic-links.php?action=generate", {
        method: "POST",
        body: formData,
      })
      const data = await response.json()

      if (data.success) {
        alert("Link: " + data.magic_link)
        document.getElementById("magicLinkForm").reset()
        this.loadMagicLinks()
      } else {
        alert(data.message)
      }
    } catch (error) {
      alert("Erro ao gerar magic link")
    }
  }

  async toggleVip(userId) {
    const formData = new FormData()
    formData.append("user_id", userId)

    try {
      const response = await fetch("api/admin-users.php?action=toggle-vip", {
        method: "POST",
        body: formData,
      })
      const data = await response.json()

      if (data.success) {
        this.loadUsers()
      }
    } catch (error) {
      console.error("Erro ao alternar VIP:", error)
    }
  }

  async toggleUserStatus(userId) {
    const newStatus = prompt("Novo status (active/inactive/blocked):")
    if (!newStatus) return

    const formData = new FormData()
    formData.append("user_id", userId)
    formData.append("status", newStatus)

    try {
      const response = await fetch("api/admin-users.php?action=toggle-status", {
        method: "POST",
        body: formData,
      })
      const data = await response.json()

      if (data.success) {
        this.loadUsers()
      }
    } catch (error) {
      console.error("Erro ao alterar status:", error)
    }
  }

  downloadLogs() {
    window.location.href = "api/export-logs.php"
  }
}

const admin = new AdminPanel()
