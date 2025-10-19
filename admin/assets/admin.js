// PWD Portal Admin Panel JavaScript

// Global variables
let sidebarOpen = true

// Initialize admin panel
document.addEventListener("DOMContentLoaded", () => {
  initializeSidebar()
  initializeNotifications()
  initializeUserDropdown()
  initializeModals()
  initializeTooltips()

  // Auto-refresh notifications every 30 seconds
  setInterval(refreshNotifications, 30000)

  // Check if on mobile
  if (window.innerWidth <= 1024) {
    sidebarOpen = false
    document.getElementById("adminSidebar").classList.remove("show")
  }
})

function initializeSidebar() {
  const sidebarToggle = document.getElementById("sidebarToggle")
  const sidebar = document.getElementById("adminSidebar")
  const mainContent = document.getElementById("mainContent")

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener("click", (e) => {
      e.stopPropagation()
      toggleSidebar()
    })

    // Close sidebar when clicking outside on mobile
    document.addEventListener("click", (e) => {
      if (window.innerWidth <= 1024) {
        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
          if (sidebar.classList.contains("show")) {
            sidebar.classList.remove("show")
            sidebarOpen = false
          }
        }
      }
    })

    // Handle window resize
    window.addEventListener("resize", () => {
      if (window.innerWidth > 1024) {
        sidebar.classList.remove("show")
        sidebar.classList.remove("collapsed")
        if (mainContent) {
          mainContent.classList.remove("sidebar-open")
        }
        sidebarOpen = true
      } else {
        sidebar.classList.remove("show")
        if (mainContent) {
          mainContent.classList.remove("sidebar-open")
        }
        sidebarOpen = false
      }
    })
  }
}

function toggleSidebar() {
  const sidebar = document.getElementById("adminSidebar")
  const mainContent = document.getElementById("mainContent")

  if (window.innerWidth <= 1024) {
    // Mobile behavior
    sidebar.classList.toggle("show")
    sidebarOpen = sidebar.classList.contains("show")

    if (sidebarOpen) {
      mainContent.classList.add("sidebar-open")
    } else {
      mainContent.classList.remove("sidebar-open")
    }
  } else {
    // Desktop behavior
    sidebar.classList.toggle("collapsed")
    mainContent.classList.toggle("expanded")
    sidebarOpen = !sidebar.classList.contains("collapsed")

    // Store preference
    localStorage.setItem("sidebarCollapsed", !sidebarOpen)
  }
}

function initializeNotifications() {
  const notificationBtn = document.getElementById("notificationBtn")
  const notificationDropdown = document.getElementById("notificationDropdown")

  if (notificationBtn && notificationDropdown) {
    notificationBtn.addEventListener("click", (e) => {
      e.stopPropagation()
      toggleNotifications()
    })

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
        notificationDropdown.classList.remove("show")
      }
    })
  }
}

function toggleNotifications() {
  const dropdown = document.getElementById("notificationDropdown")
  const userDropdown = document.getElementById("userDropdown")

  if (dropdown) {
    dropdown.classList.toggle("show")

    // Close user dropdown if open
    if (userDropdown) {
      userDropdown.classList.remove("show")
    }
  }
}

function initializeUserDropdown() {
  const userDropdownToggle = document.getElementById("userDropdownToggle")
  const userDropdown = document.getElementById("userDropdown")

  if (userDropdownToggle && userDropdown) {
    userDropdownToggle.addEventListener("click", (e) => {
      e.stopPropagation()
      toggleUserDropdown()
    })

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!userDropdown.contains(e.target) && !userDropdownToggle.contains(e.target)) {
        userDropdown.classList.remove("show")
      }
    })
  }
}

function toggleUserDropdown() {
  const dropdown = document.getElementById("userDropdown")
  const notificationDropdown = document.getElementById("notificationDropdown")

  if (dropdown) {
    dropdown.classList.toggle("show")

    // Close notification dropdown if open
    if (notificationDropdown) {
      notificationDropdown.classList.remove("show")
    }
  }
}

function refreshNotifications() {
  // Fetch new notifications from server
  fetch("api/notifications.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        updateNotificationBadge(data.unread_count)
        updateNotificationList(data.notifications)
      }
    })
    .catch((error) => {
      console.error("Failed to refresh notifications:", error)
    })
}

function updateNotificationBadge(count) {
  const badge = document.querySelector(".notification-badge")
  if (badge) {
    if (count > 0) {
      badge.textContent = count
      badge.style.display = "block"
    } else {
      badge.style.display = "none"
    }
  }
}

function updateNotificationList(notifications) {
  const list = document.querySelector(".notification-list")
  if (list && notifications) {
    list.innerHTML = notifications
      .map(
        (notification) => `
      <div class="notification-item ${notification.read ? "" : "unread"}">
        <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
        <div class="notification-content">
          <p>${notification.message}</p>
          <span>${timeAgo(notification.created_at)}</span>
        </div>
      </div>
    `,
      )
      .join("")
  }
}

function getNotificationIcon(type) {
  const icons = {
    appointment: "calendar-check",
    feedback: "comment",
    record: "id-card",
    system: "cog",
    user: "user",
  }
  return icons[type] || "bell"
}

function initializeModals() {
  // Close modals when clicking outside
  document.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal")) {
      closeModal(e.target.id)
    }
  })

  // Close modals with Escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      const openModals = document.querySelectorAll(".modal.show")
      openModals.forEach((modal) => {
        closeModal(modal.id)
      })
    }
  })
}

function showModal(modalId) {
  const modal = document.getElementById(modalId)
  if (modal) {
    modal.classList.add("show")
    document.body.style.overflow = "hidden"
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId)
  if (modal) {
    modal.classList.remove("show")
    document.body.style.overflow = ""
  }
}

function initializeTooltips() {
  // Add tooltip functionality for buttons and icons
  const tooltipElements = document.querySelectorAll("[data-tooltip]")

  tooltipElements.forEach((element) => {
    element.addEventListener("mouseenter", showTooltip)
    element.addEventListener("mouseleave", hideTooltip)
  })
}

function showTooltip(e) {
  const element = e.target
  const tooltipText = element.getAttribute("data-tooltip")

  if (tooltipText) {
    const tooltip = document.createElement("div")
    tooltip.className = "tooltip"
    tooltip.textContent = tooltipText
    tooltip.style.cssText = `
      position: absolute;
      background: #333;
      color: white;
      padding: 6px 10px;
      border-radius: 4px;
      font-size: 0.8rem;
      z-index: 10000;
      pointer-events: none;
      white-space: nowrap;
    `

    document.body.appendChild(tooltip)

    const rect = element.getBoundingClientRect()
    tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px"
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + "px"

    element._tooltip = tooltip
  }
}

function hideTooltip(e) {
  const element = e.target
  if (element._tooltip) {
    element._tooltip.remove()
    delete element._tooltip
  }
}

// Notification system
function showNotification(message, type = "info", duration = 5000) {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(".admin-notification")
  existingNotifications.forEach((notification) => notification.remove())

  // Create notification element
  const notification = document.createElement("div")
  notification.className = `admin-notification notification-${type}`
  notification.innerHTML = `
    <div class="notification-content">
      <i class="fas fa-${getNotificationTypeIcon(type)}"></i>
      <span class="notification-message">${message}</span>
      <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
    </div>
  `

  // Add styles
  notification.style.cssText = `
    position: fixed;
    top: 90px;
    right: 20px;
    z-index: 10000;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    max-width: 400px;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
  `

  // Set background color based on type
  const colors = {
    success: "#10b981",
    error: "#ef4444",
    warning: "#f59e0b",
    info: "#2c5aa0",
  }
  notification.style.backgroundColor = colors[type] || colors.info

  // Add to page
  document.body.appendChild(notification)

  // Animate in
  setTimeout(() => {
    notification.style.transform = "translateX(0)"
  }, 100)

  // Auto remove
  setTimeout(() => {
    if (notification.parentElement) {
      notification.style.transform = "translateX(100%)"
      setTimeout(() => notification.remove(), 300)
    }
  }, duration)
}

function getNotificationTypeIcon(type) {
  const icons = {
    success: "check-circle",
    error: "exclamation-circle",
    warning: "exclamation-triangle",
    info: "info-circle",
  }
  return icons[type] || "bell"
}

// Date utilities
function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  })
}

function formatDateTime(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  })
}

function timeAgo(dateString) {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now - date) / 1000)

  if (diffInSeconds < 60) {
    return "just now"
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60)
    return `${minutes} minute${minutes > 1 ? "s" : ""} ago`
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600)
    return `${hours} hour${hours > 1 ? "s" : ""} ago`
  } else if (diffInSeconds < 2592000) {
    const days = Math.floor(diffInSeconds / 86400)
    return `${days} day${days > 1 ? "s" : ""} ago`
  } else {
    return formatDate(dateString)
  }
}

// Form utilities
function validateForm(form) {
  const requiredFields = form.querySelectorAll("[required]")
  let isValid = true

  requiredFields.forEach((field) => {
    if (!field.value.trim()) {
      field.classList.add("error")
      isValid = false
    } else {
      field.classList.remove("error")
    }
  })

  return isValid
}

function resetForm(form) {
  form.reset()
  const errorFields = form.querySelectorAll(".error")
  errorFields.forEach((field) => field.classList.remove("error"))
}

// Export utilities
function exportTableToCSV(table, filename) {
  const rows = table.querySelectorAll("tr")
  const csv = []

  rows.forEach((row) => {
    const cells = row.querySelectorAll("th, td")
    const rowData = Array.from(cells).map((cell) => {
      return '"' + cell.textContent.replace(/"/g, '""') + '"'
    })
    csv.push(rowData.join(","))
  })

  const csvContent = csv.join("\n")
  const blob = new Blob([csvContent], { type: "text/csv" })
  const url = window.URL.createObjectURL(blob)

  const a = document.createElement("a")
  a.href = url
  a.download = filename || "export.csv"
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)
}

// AJAX utilities
function makeRequest(url, options = {}) {
  const defaultOptions = {
    method: "GET",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
  }

  const finalOptions = { ...defaultOptions, ...options }

  return fetch(url, finalOptions)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.json()
    })
    .catch((error) => {
      console.error("Request failed:", error)
      throw error
    })
}

// Confirmation dialogs
function confirmAction(message, callback) {
  if (confirm(message)) {
    callback()
  }
}

function confirmDelete(itemName, callback) {
  const message = `Are you sure you want to delete "${itemName}"? This action cannot be undone.`
  confirmAction(message, callback)
}

// Loading states
function showLoading(element) {
  const originalContent = element.innerHTML
  element.dataset.originalContent = originalContent
  element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'
  element.disabled = true
}

function hideLoading(element) {
  if (element.dataset.originalContent) {
    element.innerHTML = element.dataset.originalContent
    delete element.dataset.originalContent
  }
  element.disabled = false
}

// Local storage utilities
function saveToLocalStorage(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify(data))
  } catch (error) {
    console.error("Failed to save to localStorage:", error)
  }
}

function loadFromLocalStorage(key, defaultValue = null) {
  try {
    const data = localStorage.getItem(key)
    return data ? JSON.parse(data) : defaultValue
  } catch (error) {
    console.error("Failed to load from localStorage:", error)
    return defaultValue
  }
}

// Initialize saved preferences
document.addEventListener("DOMContentLoaded", () => {
  // Restore sidebar state on desktop
  if (window.innerWidth > 1024) {
    const savedSidebarState = loadFromLocalStorage("sidebarCollapsed", false)
    if (savedSidebarState) {
      const sidebar = document.getElementById("adminSidebar")
      const mainContent = document.getElementById("mainContent")
      if (sidebar && mainContent) {
        sidebar.classList.add("collapsed")
        mainContent.classList.add("expanded")
        sidebarOpen = false
      }
    }
  }
})

// Global error handler
window.addEventListener("error", (e) => {
  console.error("Global error:", e.error)
})

// Global unhandled promise rejection handler
window.addEventListener("unhandledrejection", (e) => {
  console.error("Unhandled promise rejection:", e.reason)
})

