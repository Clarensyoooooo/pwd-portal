// Enhanced script.js for PWD Portal Appointment System

// Global variables
let currentAppointment = null

// Mobile menu toggle and navigation
document.addEventListener("DOMContentLoaded", () => {
  const mobileToggle = document.querySelector(".mobile-menu-toggle")
  const navMenu = document.querySelector(".nav-menu")

  if (mobileToggle) {
    mobileToggle.addEventListener("click", () => {
      navMenu.classList.toggle("active")

      // Animate hamburger menu
      const spans = mobileToggle.querySelectorAll("span")
      spans.forEach((span, index) => {
        if (navMenu.classList.contains("active")) {
          if (index === 0) span.style.transform = "rotate(45deg) translate(5px, 5px)"
          if (index === 1) span.style.opacity = "0"
          if (index === 2) span.style.transform = "rotate(-45deg) translate(7px, -6px)"
        } else {
          span.style.transform = "none"
          span.style.opacity = "1"
        }
      })
    })
  }

  // Smooth scrolling for navigation links
  const navLinks = document.querySelectorAll('.nav-menu a[href^="#"]')
  navLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault()
      const targetId = this.getAttribute("href")
      const targetSection = document.querySelector(targetId)
      if (targetSection) {
        targetSection.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })

        // Close mobile menu if open
        if (navMenu.classList.contains("active")) {
          navMenu.classList.remove("active")
          const spans = mobileToggle.querySelectorAll("span")
          spans.forEach((span) => {
            span.style.transform = "none"
            span.style.opacity = "1"
          })
        }
      }
    })
  })

  // Initialize modals
  initializeModals()

  // Initialize date restrictions
  initializeDateRestrictions()

  // Initialize animations
  initializeAnimations()

  // Initialize star rating
  initializeStarRating()

  // Add accessibility features
  addAccessibilityFeatures()

  // Update UI based on login status
  updateUIForUser()

  // Pre-fill feedback form if user is logged in
  prefillFeedbackForm()
})

// Authentication Functions
function showLoginModal() {
  document.getElementById("loginModal").style.display = "block"
}

function showRegisterModal() {
  document.getElementById("registerModal").style.display = "block"
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none"
}

function switchToRegister() {
  closeModal("loginModal")
  showRegisterModal()
}

function switchToLogin() {
  closeModal("registerModal")
  showLoginModal()
}

async function handleLogin(event) {
  event.preventDefault()

  const formData = new FormData(event.target)
  formData.append("action", "login")

  try {
    const response = await fetch("auth.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message, "success")
      closeModal("loginModal")

      // Update global user variable
      window.currentUser = result.user

      // Update UI
      updateUIForUser()

      // Reload page to update header
      setTimeout(() => {
        window.location.reload()
      }, 1000)
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Login failed. Please try again.", "error")
  }
}

async function handleRegister(event) {
  event.preventDefault()

  const formData = new FormData(event.target)
  formData.append("action", "register")

  // Validate password confirmation
  const password = formData.get("password")
  const confirmPassword = formData.get("confirm_password")

  if (password !== confirmPassword) {
    showNotification("Passwords do not match", "error")
    return
  }

  try {
    const response = await fetch("auth.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message, "success")
      closeModal("registerModal")

      // Update global user variable
      window.currentUser = result.user

      // Update UI
      updateUIForUser()

      // Reload page to update header
      setTimeout(() => {
        window.location.reload()
      }, 1000)
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Registration failed. Please try again.", "error")
  }
}

async function logout() {
  try {
    const formData = new FormData()
    formData.append("action", "logout")

    const response = await fetch("auth.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message, "success")
      window.currentUser = null

      // Reload page
      setTimeout(() => {
        window.location.reload()
      }, 1000)
    }
  } catch (error) {
    showNotification("Logout failed. Please try again.", "error")
  }
}

// Appointment Functions
function startApplication() {
  if (!window.currentUser) {
    showNotification("Please login or register to book an appointment", "warning")
    showLoginModal()
    return
  }

  document.getElementById("appointmentModal").style.display = "block"
}

async function handleAppointmentBooking(event) {
  event.preventDefault()

  const formData = new FormData(event.target)
  formData.append("action", "book_appointment")

  try {
    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message, "success")
      closeModal("appointmentModal")

      // Show the reference number and scroll to tracking
      setTimeout(() => {
        document.getElementById("trackingNumber").value = result.appointment.reference_number
        trackAppointment()

        // Scroll to tracking section
        document.querySelector(".track-appointment").scrollIntoView({
          behavior: "smooth",
        })
      }, 1000)
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Booking failed. Please try again.", "error")
  }
}

async function trackAppointment() {
  const trackingNumber = document.getElementById("trackingNumber").value.trim()

  if (!trackingNumber) {
    showNotification("Please enter a reference number", "error")
    return
  }

  // Show loading state
  const button = event?.target || document.querySelector(".btn-track")
  const originalText = button.textContent
  button.textContent = "Tracking..."
  button.disabled = true

  try {
    const formData = new FormData()
    formData.append("action", "track_appointment")
    formData.append("reference_number", trackingNumber)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      currentAppointment = result.appointment
      displayAppointmentDetails(result.appointment)
      showNotification("Appointment found!", "success")
    } else {
      showNotification(result.error, "error")
      hideAppointmentDetails()
    }
  } catch (error) {
    showNotification("Tracking failed. Please try again.", "error")
    hideAppointmentDetails()
  } finally {
    button.textContent = originalText
    button.disabled = false
  }
}

function displayAppointmentDetails(appointment) {
  const appointmentSection = document.getElementById("appointmentStatusSection")
  const noTrackingMessage = document.getElementById("noTrackingMessage")

  // Hide no tracking message and show appointment details
  noTrackingMessage.style.display = "none"
  appointmentSection.style.display = "block"

  // Update status progress
  updateStatusProgress(appointment)

  // Update appointment details card
  updateAppointmentDetailsCard(appointment)

  // Show SMS verification if needed
  updateSMSVerificationSection(appointment)

  // Update final confirmation
  updateFinalConfirmation(appointment)

  // Update timeline
  updateAppointmentTimeline(appointment)

  // Animate appearance
  appointmentSection.style.opacity = "0"
  appointmentSection.style.transform = "translateY(20px)"

  setTimeout(() => {
    appointmentSection.style.transition = "all 0.5s ease"
    appointmentSection.style.opacity = "1"
    appointmentSection.style.transform = "translateY(0)"
  }, 100)
}

function updateStatusProgress(appointment) {
  const statusProgress = document.getElementById("statusProgress")

  const steps = [
    {
      id: "step1",
      title: "Appointment Details Submitted",
      description: "You selected a date and provided your info.",
      completed: true,
    },
    {
      id: "step2",
      title: "Email Verification Sent",
      description: "A 6-digit confirmation code was sent to your email.",
      completed: appointment.sms_verification_sent,
    },
    {
      id: "step3",
      title: "Appointment Confirmed",
      description: "Your appointment has been booked. Please show up on-site as scheduled.",
      completed: appointment.status === "confirmed" || appointment.status === "completed",
    },
  ]

  statusProgress.innerHTML = steps
    .map(
      (step) => `
    <div class="status-step ${step.completed ? "completed" : ""}" id="${step.id}">
      <div class="status-circle">
        <i class="fas fa-check"></i>
      </div>
      <div class="status-content">
        <h4>${step.title}</h4>
        <p>${step.description}</p>
      </div>
    </div>
  `,
    )
    .join("")
}

function updateAppointmentDetailsCard(appointment) {
  const detailsCard = document.getElementById("appointmentDetailsCard")

  const appointmentDate = appointment.actual_date || appointment.preferred_date
  const appointmentTime = appointment.actual_time || appointment.preferred_time

  detailsCard.innerHTML = `
    <div class="details-grid">
      <div class="detail-item">
        <span class="detail-label">Applicant:</span>
        <span class="detail-value">${appointment.applicant_name}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Contact Number:</span>
        <span class="detail-value">${appointment.contact_number}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Date Submitted:</span>
        <span class="detail-value">${formatDate(appointment.created_at)}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Appointment:</span>
        <span class="detail-value">${formatDate(appointmentDate)} ${formatTime(appointmentTime)}</span>
      </div>
      <div class="detail-item full-width">
        <span class="detail-label">Next Steps:</span>
        <span class="detail-value">Bring all required documents to your scheduled appointment on ${formatDate(appointmentDate)}</span>
      </div>
    </div>
  `
}

function updateSMSVerificationSection(appointment) {
  const smsSection = document.getElementById("smsVerificationSection")

  if (appointment.sms_verification_sent && !appointment.sms_verified_at) {
    smsSection.style.display = "block"
  } else {
    smsSection.style.display = "none"
  }
}

async function verifySMS() {
  const verificationCode = document.getElementById("smsVerificationCode").value.trim()

  if (!verificationCode || verificationCode.length !== 6) {
    showNotification("Please enter a valid 6-digit code", "error")
    return
  }

  try {
    const formData = new FormData()
    formData.append("action", "verify_sms")
    formData.append("reference_number", currentAppointment.reference_number)
    formData.append("verification_code", verificationCode)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message, "success")

      // Refresh appointment details
      trackAppointment()
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Email verification failed. Please try again.", "error")
  }
}

function updateFinalConfirmation(appointment) {
  const finalConfirmation = document.getElementById("finalConfirmation")

  if (appointment.status === "confirmed" || appointment.status === "completed") {
    finalConfirmation.style.display = "block"
    finalConfirmation.innerHTML = `
      <div class="confirmation-content">
        <i class="fas fa-check-circle"></i>
        <div class="confirmation-text">
          <h4>Appointment Confirmed</h4>
          <p>Your appointment has been successfully confirmed. Please arrive 15 minutes before your scheduled time and bring all required documents.</p>
        </div>
      </div>
      <div class="confirmation-footer">
        <span class="reference-label">Appointment Reference</span>
        <span class="reference-number">${appointment.reference_number}</span>
        <button class="btn-save" onclick="saveAppointmentDetails()">
          <i class="fas fa-download"></i> Save Details
        </button>
      </div>
    `
  } else {
    finalConfirmation.style.display = "none"
  }
}

function updateAppointmentTimeline(appointment) {
  const timeline = document.getElementById("appointmentTimeline")

  const timelineItems = []

  // Always show submitted
  timelineItems.push({
    title: "Appointment Details Submitted",
    date: appointment.created_at,
    description: "You selected a date and provided your information.",
    completed: true,
  })

  // Show SMS sent if applicable
  if (appointment.sms_verification_sent) {
    timelineItems.push({
      title: "Email Verification Sent",
      date: appointment.created_at, // In real app, track SMS sent time
      description: "A 6-digit confirmation code was sent to your email.",
      completed: true,
    })
  }

  // Show confirmed if applicable
  if (appointment.confirmed_at) {
    timelineItems.push({
      title: "Appointment Confirmed",
      date: appointment.confirmed_at,
      description: "Your appointment is now booked. Please show up on-site as scheduled.",
      completed: true,
    })
  }

  timeline.innerHTML = `
    <h4>Appointment Timeline</h4>
    <div class="timeline-items">
      ${timelineItems
        .map(
          (item) => `
        <div class="timeline-item ${item.completed ? "completed" : ""}">
          <div class="timeline-dot"></div>
          <div class="timeline-content">
            <h5>${item.title}</h5>
            <span class="timeline-date">${formatDateTime(item.date)}</span>
            <p>${item.description}</p>
          </div>
        </div>
      `,
        )
        .join("")}
    </div>
  `
}

function hideAppointmentDetails() {
  const appointmentSection = document.getElementById("appointmentStatusSection")
  const noTrackingMessage = document.getElementById("noTrackingMessage")

  appointmentSection.style.display = "none"
  noTrackingMessage.style.display = "flex"
}

function saveAppointmentDetails() {
  if (!currentAppointment) {
    showNotification("No appointment details to save", "error")
    return
  }

  const appointmentDetails = `
PWD APPOINTMENT DETAILS
======================

Reference Number: ${currentAppointment.reference_number}
Applicant Name: ${currentAppointment.applicant_name}
Appointment Date & Time: ${formatDate(currentAppointment.actual_date || currentAppointment.preferred_date)} ${formatTime(currentAppointment.actual_time || currentAppointment.preferred_time)}
Contact Number: ${currentAppointment.contact_number}
Email: ${currentAppointment.email}
Date Submitted: ${formatDate(currentAppointment.created_at)}
Status: ${currentAppointment.status.toUpperCase()}

IMPORTANT REMINDERS:
- Arrive 15 minutes before your scheduled time
- Bring all required documents
- Bring a valid government-issued ID
- Contact us at 8888-1000 for any concerns

Generated on: ${new Date().toLocaleString()}
  `

  // Create and download the file
  const blob = new Blob([appointmentDetails], { type: "text/plain" })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = `PWD_Appointment_${currentAppointment.reference_number.replace(/[^a-zA-Z0-9]/g, "_")}.txt`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)

  showNotification("Appointment details saved successfully!", "success")
}

// Feedback Functions
async function handleFeedback(event) {
  event.preventDefault()

  const formData = new FormData(event.target)
  formData.append("action", "submit_feedback")

  try {
    const response = await fetch("process_feedback.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message, "success")

      // Reset form
      event.target.reset()
      resetStarRating()

      // Pre-fill user info again if logged in
      prefillFeedbackForm()
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Failed to submit feedback. Please try again.", "error")
  }
}

function prefillFeedbackForm() {
  if (window.currentUser) {
    const nameField = document.getElementById("feedbackName")
    const emailField = document.getElementById("feedbackEmail")

    if (nameField && !nameField.value) {
      nameField.value = window.currentUser.name || ""
    }
    if (emailField && !emailField.value) {
      emailField.value = window.currentUser.email || ""
    }
  }
}

function initializeStarRating() {
  const starInputs = document.querySelectorAll('.star-rating input[type="radio"]')
  const ratingText = document.querySelector(".rating-text")

  starInputs.forEach((input) => {
    input.addEventListener("change", function () {
      const rating = this.value
      const ratingTexts = {
        1: "Very Poor",
        2: "Poor",
        3: "Average",
        4: "Good",
        5: "Excellent",
      }

      if (ratingText) {
        ratingText.textContent = ratingTexts[rating] || "Click to rate"
      }
    })
  })
}

function resetStarRating() {
  const starInputs = document.querySelectorAll('.star-rating input[type="radio"]')
  const ratingText = document.querySelector(".rating-text")

  starInputs.forEach((input) => {
    input.checked = false
  })

  if (ratingText) {
    ratingText.textContent = "Click to rate"
  }
}

// Program Details Functions
function showProgramDetails(programType) {
  const modal = document.getElementById("programModal")
  const title = document.getElementById("programModalTitle")
  const content = document.getElementById("programModalContent")

  const programData = {
    mobility: {
      title: "Mobility Assistance Program",
      icon: "fas fa-wheelchair",
      description: "Comprehensive support for mobility needs of PWDs",
      details: [
        "Free wheelchair provision for qualified beneficiaries",
        "Mobility aids including crutches, walkers, and canes",
        "Transportation assistance for medical appointments",
        "Home accessibility modifications and ramps",
        "Maintenance and repair services for mobility equipment",
      ],
      eligibility: [
        "Registered PWD with valid ID",
        "Medical certification of mobility impairment",
        "Proof of income (for subsidized services)",
        "Barangay certification of residency",
      ],
      contact: "Mobility Unit: 8888-1001",
    },
    education: {
      title: "Educational Support Program",
      icon: "fas fa-graduation-cap",
      description: "Educational opportunities and support for PWD students",
      details: [
        "Scholarship grants for elementary to college level",
        "Free learning materials and assistive devices",
        "Special education programs and inclusive classrooms",
        "Tutorial and mentoring services",
        "Career guidance and counseling",
      ],
      eligibility: [
        "PWD student with valid ID",
        "Academic records and transcripts",
        "Certificate of enrollment",
        "Family income certification",
      ],
      contact: "Education Unit: 8888-1002",
    },
    livelihood: {
      title: "Livelihood Training Program",
      icon: "fas fa-briefcase",
      description: "Skills development and employment opportunities",
      details: [
        "Vocational training in various skills",
        "Job placement assistance and referrals",
        "Business startup loans and grants",
        "Entrepreneurship training and mentoring",
        "Cooperative formation and management",
      ],
      eligibility: ["PWD aged 18-65 years", "Valid PWD ID", "Basic literacy skills", "Commitment to complete training"],
      contact: "Livelihood Unit: 8888-1003",
    },
    healthcare: {
      title: "Healthcare Access Program",
      icon: "fas fa-heartbeat",
      description: "Comprehensive healthcare services for PWDs",
      details: [
        "Free medical consultations and check-ups",
        "Subsidized medications and treatments",
        "Physical and occupational therapy services",
        "Mental health counseling and support",
        "Health insurance enrollment assistance",
      ],
      eligibility: [
        "Valid PWD ID",
        "Medical records and history",
        "PhilHealth membership (if applicable)",
        "Referral from barangay health worker",
      ],
      contact: "Healthcare Unit: 8888-1004",
    },
    technology: {
      title: "Assistive Technology Program",
      icon: "fas fa-laptop",
      description: "Technology solutions for independent living",
      details: [
        "Hearing aids and communication devices",
        "Computer access software and hardware",
        "Smart home technology installation",
        "Mobile apps for PWD assistance",
        "Training on assistive technology use",
      ],
      eligibility: [
        "Valid PWD ID",
        "Assessment by technology specialist",
        "Basic technology literacy",
        "Commitment to proper device care",
      ],
      contact: "Technology Unit: 8888-1005",
    },
    community: {
      title: "Community Integration Program",
      icon: "fas fa-users",
      description: "Building inclusive communities for PWDs",
      details: [
        "Social activities and recreational programs",
        "Support groups and peer counseling",
        "Advocacy and awareness campaigns",
        "Community accessibility projects",
        "Volunteer and leadership opportunities",
      ],
      eligibility: [
        "Valid PWD ID",
        "Interest in community participation",
        "Willingness to engage with others",
        "Commitment to program activities",
      ],
      contact: "Community Unit: 8888-1006",
    },
  }

  const program = programData[programType]
  if (!program) return

  title.textContent = program.title
  content.innerHTML = `
    <div class="program-detail-content">
      <div class="program-header">
        <i class="${program.icon}"></i>
        <p class="program-description">${program.description}</p>
      </div>
      
      <div class="program-section">
        <h4><i class="fas fa-list"></i> Program Benefits</h4>
        <ul class="program-list">
          ${program.details.map((detail) => `<li>${detail}</li>`).join("")}
        </ul>
      </div>
      
      <div class="program-section">
        <h4><i class="fas fa-check-circle"></i> Eligibility Requirements</h4>
        <ul class="program-list">
          ${program.eligibility.map((req) => `<li>${req}</li>`).join("")}
        </ul>
      </div>
      
      <div class="program-section">
        <h4><i class="fas fa-phone"></i> Contact Information</h4>
        <p class="program-contact">${program.contact}</p>
        <p class="program-contact">General Hotline: 8888-1000</p>
      </div>
      
      <div class="program-actions">
        <button class="btn-primary" onclick="startApplication()">Apply Now</button>
        <button class="btn-secondary" onclick="closeModal('programModal')">Close</button>
      </div>
    </div>
  `

  modal.style.display = "block"
}

// FAQ toggle function
function toggleFAQ(element) {
  const answer = element.nextElementSibling
  const icon = element.querySelector("i")

  // Close all other FAQ items
  const allAnswers = document.querySelectorAll(".faq-answer")
  const allIcons = document.querySelectorAll(".faq-question i")

  allAnswers.forEach((item) => {
    if (item !== answer && item.classList.contains("active")) {
      item.classList.remove("active")
    }
  })

  allIcons.forEach((item) => {
    if (item !== icon) {
      item.style.transform = "rotate(0deg)"
    }
  })

  // Toggle current FAQ item
  answer.classList.toggle("active")

  if (answer.classList.contains("active")) {
    icon.style.transform = "rotate(180deg)"
  } else {
    icon.style.transform = "rotate(0deg)"
  }
}

// Utility functions
function updateUIForUser() {
  // Update any UI elements based on login status
  if (window.currentUser) {
    // User is logged in
    console.log("User logged in:", window.currentUser)
  } else {
    // User is not logged in
    console.log("User not logged in")
  }
}

function initializeDateRestrictions() {
  const dateInput = document.getElementById("preferredDate")
  if (dateInput) {
    // Set minimum date to tomorrow
    const tomorrow = new Date()
    tomorrow.setDate(tomorrow.getDate() + 1)
    dateInput.min = tomorrow.toISOString().split("T")[0]

    // Set maximum date to 30 days from now
    const maxDate = new Date()
    maxDate.setDate(maxDate.getDate() + 30)
    dateInput.max = maxDate.toISOString().split("T")[0]
  }
}

function initializeModals() {
  // Close modals when clicking outside
  window.onclick = (event) => {
    const modals = document.querySelectorAll(".modal")
    modals.forEach((modal) => {
      if (event.target === modal) {
        modal.style.display = "none"
      }
    })
  }
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  })
}

function formatTime(timeString) {
  const [hours, minutes] = timeString.split(":")
  const date = new Date()
  date.setHours(Number.parseInt(hours), Number.parseInt(minutes))
  return date.toLocaleTimeString("en-US", {
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  })
}

function formatDateTime(dateTimeString) {
  const date = new Date(dateTimeString)
  return (
    date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    }) +
    ", " +
    date.toLocaleTimeString("en-US", {
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
    })
  )
}

function showNotification(message, type = "info") {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(".notification")
  existingNotifications.forEach((notification) => notification.remove())

  // Create notification element
  const notification = document.createElement("div")
  notification.className = `notification notification-${type}`
  notification.innerHTML = `
    <div class="notification-content">
      <span class="notification-message">${message}</span>
      <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
    </div>
  `

  // Add styles
  notification.style.cssText = `
    position: fixed;
    top: 100px;
    right: 20px;
    z-index: 10000;
    padding: 15px 20px;
    border-radius: 5px;
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

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentElement) {
      notification.style.transform = "translateX(100%)"
      setTimeout(() => notification.remove(), 300)
    }
  }, 5000)
}

function initializeAnimations() {
  const animateElements = document.querySelectorAll(".step, .program-card, .partner-logo")
  animateElements.forEach((element) => {
    element.style.opacity = "0"
    element.style.transform = "translateY(20px)"
    element.style.transition = "opacity 0.6s ease, transform 0.6s ease"
  })

  // Trigger animations on scroll
  window.addEventListener("scroll", handleScrollAnimations)

  // Initial check for elements in view
  handleScrollAnimations()
}

function handleScrollAnimations() {
  const scrolled = window.pageYOffset
  const windowHeight = window.innerHeight

  // Animate elements when they come into view
  const animateElements = document.querySelectorAll(".step, .program-card, .partner-logo, .faq-item")
  animateElements.forEach((element) => {
    const elementTop = element.offsetTop
    const elementBottom = elementTop + element.offsetHeight
    const viewportTop = scrolled
    const viewportBottom = viewportTop + windowHeight

    if (elementBottom > viewportTop && elementTop < viewportBottom) {
      element.style.opacity = "1"
      element.style.transform = "translateY(0)"
    }
  })

  // Update navbar on scroll
  const header = document.querySelector(".header")
  if (header) {
    if (scrolled > 100) {
      header.style.background = "rgba(255, 255, 255, 0.95)"
      header.style.backdropFilter = "blur(10px)"
    } else {
      header.style.background = "#fff"
      header.style.backdropFilter = "none"
    }
  }
}

function addAccessibilityFeatures() {
  // Add keyboard navigation for FAQ items
  const faqQuestions = document.querySelectorAll(".faq-question")
  faqQuestions.forEach((question) => {
    question.setAttribute("tabindex", "0")
    question.setAttribute("role", "button")
    question.setAttribute("aria-expanded", "false")

    question.addEventListener("keypress", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault()
        toggleFAQ(this)
        this.setAttribute("aria-expanded", this.nextElementSibling.classList.contains("active") ? "true" : "false")
      }
    })
  })

  // Add focus indicators
  const focusableElements = document.querySelectorAll("button, input, textarea, select, a[href], [tabindex]")
  focusableElements.forEach((element) => {
    element.addEventListener("focus", function () {
      this.style.outline = "2px solid #2c5aa0"
      this.style.outlineOffset = "2px"
    })

    element.addEventListener("blur", function () {
      this.style.outline = "none"
    })
  })
}

// Enhanced tracking input with Enter key support
document.addEventListener("DOMContentLoaded", () => {
  const trackingInput = document.getElementById("trackingNumber")
  if (trackingInput) {
    trackingInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        e.preventDefault()
        trackAppointment()
      }
    })

    // Add input formatting for reference numbers
    trackingInput.addEventListener("input", (e) => {
      let value = e.target.value.toUpperCase()
      // Auto-format PWD reference numbers
      if (value.length > 0 && !value.startsWith("PWD")) {
        if (value.match(/^\d/)) {
          value = "PWD-" + value
        }
      }
      e.target.value = value
    })
  }
})

// Initialize everything when page loads
window.addEventListener("load", () => {
  // Add loaded class to body for CSS animations
  document.body.classList.add("loaded")

  // Initialize any additional features
  console.log("PWD Portal with Real Database Integration loaded successfully!")

  // Show welcome message for first-time visitors
  if (!localStorage.getItem("pwd_portal_visited")) {
    setTimeout(() => {
      if (window.currentUser) {
        showNotification(
          `Welcome back, ${window.currentUser.name}! You can now book appointments and track them in real-time.`,
          "info",
        )
      } else {
        showNotification(
          "Welcome to PWD Portal! Register or login to book appointments and track them in real-time.",
          "info",
        )
      }
      localStorage.setItem("pwd_portal_visited", "true")
    }, 2000)
  }
})

// Add keyboard shortcuts
document.addEventListener("keydown", (e) => {
  // Ctrl/Cmd + K to focus search
  if ((e.ctrlKey || e.metaKey) && e.key === "k") {
    e.preventDefault()
    const trackingInput = document.getElementById("trackingNumber")
    if (trackingInput) {
      trackingInput.focus()
      trackingInput.select()
    }
  }

  // Escape to close notifications and modals
  if (e.key === "Escape") {
    const notifications = document.querySelectorAll(".notification")
    notifications.forEach((notification) => notification.remove())

    const modals = document.querySelectorAll(".modal")
    modals.forEach((modal) => {
      if (modal.style.display === "block") {
        modal.style.display = "none"
      }
    })
  }
})
