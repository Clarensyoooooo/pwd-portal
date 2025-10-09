// Enhanced script.js for Multi-Step PWD Portal Appointment System

// Global variables
let currentAppointment = null
let currentStep = 1
let maxStep = 1
let pwdStatus = null // 'existing' or 'new'
let verifiedPWD = null

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

  // Initialize email checking for new applicants
  initializeEmailCheck()
})

// Multi-Step Form Functions
function startApplication() {
  // Show terms modal first
  document.getElementById("termsModal").style.display = "block"
}

function acceptTerms() {
  const checkbox = document.getElementById("termsCheckbox")

  if (!checkbox.checked) {
    showNotification("Please read and accept the terms and conditions to continue", "error")
    return
  }

  closeModal("termsModal")

  // Reset form state
  resetMultiStepForm()

  // Show appointment modal at step 1
  document.getElementById("appointmentModal").style.display = "block"
}

function resetMultiStepForm() {
  currentStep = 1
  maxStep = 1
  pwdStatus = null
  verifiedPWD = null

  // Reset form
  document.getElementById("appointmentForm").reset()

  // Hide all steps
  document.querySelectorAll(".form-step").forEach((step) => {
    step.classList.remove("active")
  })

  // Show first step
  document.querySelector('.form-step[data-step="1"]').classList.add("active")

  // Reset progress
  updateProgress()
}

function handlePWDStatus(status) {
  pwdStatus = status

  // Wait a bit for visual feedback
  setTimeout(() => {
    if (status === "existing") {
      // Go to existing PWD verification
      goToStep("2a")
    } else {
      // Go to new applicant personal info
      goToStep("2b")
    }
  }, 300)
}

async function verifyExistingPWD() {
  const pwdId = document.getElementById("pwdIdNumber").value.trim()
  const firstName = document.getElementById("verifyFirstName").value.trim()
  const lastName = document.getElementById("verifyLastName").value.trim()
  const dob = document.getElementById("verifyDateOfBirth").value

  if (!firstName || !lastName || !dob) {
    showNotification("Please fill in all required fields", "error")
    return
  }

  // Show loading
  const button = event.target
  const originalText = button.innerHTML
  button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...'
  button.disabled = true

  try {
    const formData = new FormData()
    formData.append("action", "verify_pwd")
    formData.append("pwd_id_number", pwdId)
    formData.append("first_name", firstName)
    formData.append("last_name", lastName)
    formData.append("date_of_birth", dob)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    const verificationResult = document.getElementById("verificationResult")

    if (result.success) {
      verifiedPWD = result.pwd_data

      verificationResult.className = "verification-result success"
      verificationResult.innerHTML = `
        <h4><i class="fas fa-check-circle"></i> Verification Successful!</h4>
        <p>We found your PWD record in our system.</p>
        <div class="verified-info">
          <p><strong>Name:</strong> ${verifiedPWD.full_name}</p>
          <p><strong>PWD ID:</strong> ${verifiedPWD.pwd_id_number || "Not available"}</p>
          <p><strong>Disability Type:</strong> ${verifiedPWD.disability_type}</p>
        </div>
        <button type="button" class="btn-primary btn-block" onclick="proceedAfterVerification()" style="margin-top: 15px;">
          Continue to Service Selection <i class="fas fa-arrow-right"></i>
        </button>
      `
      verificationResult.style.display = "block"
    } else {
      verificationResult.className = "verification-result error"
      verificationResult.innerHTML = `
        <h4><i class="fas fa-times-circle"></i> Verification Failed</h4>
        <p>${result.error}</p>
        <p>Please check your information and try again, or contact our office at 8888-1000 for assistance.</p>
      `
      verificationResult.style.display = "block"
    }
  } catch (error) {
    showNotification("Verification failed. Please try again.", "error")
  } finally {
    button.innerHTML = originalText
    button.disabled = false
  }
}

function proceedAfterVerification() {
  goToStep("3a")
}

function nextStep() {
  // Validate current step before proceeding
  const currentStepEl = document.querySelector(`.form-step[data-step="${currentStep}"]`)
  const requiredInputs = currentStepEl.querySelectorAll("[required]")

  let isValid = true
  requiredInputs.forEach((input) => {
    if (!input.value.trim()) {
      isValid = false
      input.style.borderColor = "#ef4444"
    } else {
      input.style.borderColor = "#e2e8f0"
    }
  })

  if (!isValid) {
    showNotification("Please fill in all required fields", "error")
    return
  }

  // Determine next step based on current step and path
  if (currentStep === "2b") {
    goToStep("3b")
  } else if (currentStep === "3a" || currentStep === "3b") {
    // Both paths converge to step 4
    prepareScheduleStep()
    goToStep("4")
  }
}

function previousStep() {
  // Determine previous step based on current step and path
  if (currentStep === "2a" || currentStep === "2b") {
    goToStep("1")
  } else if (currentStep === "3a") {
    goToStep("2a")
  } else if (currentStep === "3b") {
    goToStep("2b")
  } else if (currentStep === "4") {
    if (pwdStatus === "existing") {
      goToStep("3a")
    } else {
      goToStep("3b")
    }
  }
}

function goToStep(stepId) {
  // Hide all steps
  document.querySelectorAll(".form-step").forEach((step) => {
    step.classList.remove("active")
  })

  // Show target step
  const targetStep = document.querySelector(`.form-step[data-step="${stepId}"]`)
  if (targetStep) {
    targetStep.classList.add("active")
    currentStep = stepId

    // Update max step for progress
    const numericStep = typeof stepId === "string" ? Number.parseInt(stepId.charAt(0)) : stepId
    if (numericStep > maxStep) {
      maxStep = numericStep
    }

    updateProgress()

    // Scroll to top of modal
    document.querySelector(".modal-content").scrollTop = 0
  }
}

function updateProgress() {
  const numericStep = typeof currentStep === "string" ? Number.parseInt(currentStep.charAt(0)) : currentStep
  const progressPercent = (numericStep / 4) * 100

  // Update progress bar
  document.getElementById("progressFill").style.width = `${progressPercent}%`

  // Update progress steps
  document.querySelectorAll(".progress-step").forEach((step) => {
    const stepNum = Number.parseInt(step.dataset.step)
    step.classList.remove("active", "completed")

    if (stepNum < numericStep) {
      step.classList.add("completed")
    } else if (stepNum === numericStep) {
      step.classList.add("active")
    }
  })
}

function prepareScheduleStep() {
  const summary = document.getElementById("appointmentSummary")
  const requirementsList = document.getElementById("requirementsList")

  let summaryHTML = '<h4><i class="fas fa-info-circle"></i> Appointment Summary</h4>'
  let requirementsHTML = ""

  if (pwdStatus === "existing" && verifiedPWD) {
    const serviceType = document.querySelector('input[name="appointment_type"]:checked')?.value || ""

    summaryHTML += `
      <div class="summary-item">
        <span class="summary-label">Applicant Type:</span>
        <span class="summary-value">Existing PWD</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Name:</span>
        <span class="summary-value">${verifiedPWD.full_name}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Service Type:</span>
        <span class="summary-value">${formatServiceType(serviceType)}</span>
      </div>
    `

    // Requirements based on service type
    if (serviceType === "renewal") {
      requirementsHTML = `
        <li>Bring your current PWD ID</li>
        <li>Updated medical certificate (if disability status changed)</li>
        <li>2 recent 1x1 ID pictures</li>
        <li>Valid government-issued ID</li>
      `
    } else if (serviceType === "update") {
      requirementsHTML = `
        <li>Bring your current PWD ID</li>
        <li>Documents supporting the information update</li>
        <li>Valid government-issued ID</li>
      `
    } else if (serviceType === "replacement") {
      requirementsHTML = `
        <li>Affidavit of Loss (if lost)</li>
        <li>Police report (if applicable)</li>
        <li>2 recent 1x1 ID pictures</li>
        <li>Valid government-issued ID</li>
        <li>Payment for replacement fee</li>
      `
    }
  } else {
    const firstName = document.getElementById("firstName").value
    const lastName = document.getElementById("lastName").value
    const disabilityType = document.getElementById("disabilityType").value

    summaryHTML += `
      <div class="summary-item">
        <span class="summary-label">Applicant Type:</span>
        <span class="summary-value">New Application</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Name:</span>
        <span class="summary-value">${firstName} ${lastName}</span>
      </div>
      <div class="summary-item">
        <span class="summary-label">Disability Type:</span>
        <span class="summary-value">${disabilityType}</span>
      </div>
    `

    requirementsHTML = `
      <li>Medical certificate from licensed physician</li>
      <li>Barangay certificate of residency</li>
      <li>2 recent 1x1 ID pictures</li>
      <li>Valid government-issued ID</li>
      <li>Birth certificate (original and photocopy)</li>
    `
  }

  summary.innerHTML = summaryHTML
  requirementsList.innerHTML = requirementsHTML
}

function formatServiceType(type) {
  const types = {
    renewal: "PWD ID Renewal",
    update: "Update Information",
    replacement: "ID Replacement",
    new_application: "New PWD ID Application",
  }
  return types[type] || type
}

async function handleAppointmentBooking(event) {
  event.preventDefault()

  const formData = new FormData(event.target)
  formData.append("action", "book_appointment")
  formData.append("pwd_status", pwdStatus)
  formData.append("terms_accepted", "true")

  // If existing PWD, add verified data
  if (pwdStatus === "existing" && verifiedPWD) {
    formData.append("user_id", verifiedPWD.id)
    formData.append("first_name", verifiedPWD.first_name)
    formData.append("last_name", verifiedPWD.last_name)
    formData.append("email", verifiedPWD.email)
    formData.append("phone", verifiedPWD.phone)
    formData.append("date_of_birth", verifiedPWD.date_of_birth)
    formData.append("address", verifiedPWD.address)
    formData.append("disability_type", verifiedPWD.disability_type)
  } else {
    // New applicant - set appointment type
    formData.set("appointment_type", "new_application")
  }

  // Show loading state
  const submitBtn = event.target.querySelector('button[type="submit"]')
  const originalText = submitBtn.innerHTML
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...'
  submitBtn.disabled = true

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

      // Reset form
      resetMultiStepForm()
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Booking failed. Please try again.", "error")
  } finally {
    submitBtn.innerHTML = originalText
    submitBtn.disabled = false
  }
}

function initializeEmailCheck() {
  const emailInput = document.getElementById("email")
  if (emailInput) {
    let emailCheckTimeout = null

    emailInput.addEventListener("input", function () {
      clearTimeout(emailCheckTimeout)

      const email = this.value.trim()

      // Clear previous messages
      const existingMessage = document.querySelector(".email-check-message")
      if (existingMessage) {
        existingMessage.remove()
      }

      if (email && email.includes("@")) {
        emailCheckTimeout = setTimeout(() => {
          checkEmailAvailability(email)
        }, 500)
      }
    })
  }
}

async function checkEmailAvailability(email) {
  try {
    const formData = new FormData()
    formData.append("action", "check_email")
    formData.append("email", email)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    const emailInput = document.getElementById("email")
    const existingMessage = document.querySelector(".email-check-message")
    if (existingMessage) {
      existingMessage.remove()
    }

    if (!result.available) {
      const message = document.createElement("div")
      message.className = "email-check-message email-unavailable"
      message.innerHTML = `
        <i class="fas fa-exclamation-circle"></i>
        This email already has a ${result.appointment.status} appointment (Ref: ${result.appointment.reference_number}).
        Please use a different email or complete your existing appointment first.
      `
      emailInput.parentElement.appendChild(message)
      emailInput.style.borderColor = "#ef4444"
    } else {
      emailInput.style.borderColor = "#10b981"
    }
  } catch (error) {
    console.error("Error checking email:", error)
  }
}

// Tracking Functions
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
      title: "SMS Verification Sent",
      description: "A 6-digit confirmation code was sent to your number.",
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
    showNotification("SMS verification failed. Please try again.", "error")
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
      title: "SMS Verification Sent",
      date: appointment.created_at,
      description: "A 6-digit confirmation code was sent to your number.",
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
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Failed to submit feedback. Please try again.", "error")
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
function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none"
}

function initializeDateRestrictions() {
  const dateInput = document.getElementById("preferredDate")
  const dobInput = document.getElementById("dateOfBirth")
  const verifyDobInput = document.getElementById("verifyDateOfBirth")

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

  if (dobInput) {
    // Set maximum date to today for date of birth
    const today = new Date()
    dobInput.max = today.toISOString().split("T")[0]

    // Set minimum date to 120 years ago
    const minDate = new Date()
    minDate.setFullYear(minDate.getFullYear() - 120)
    dobInput.min = minDate.toISOString().split("T")[0]
  }

  if (verifyDobInput) {
    // Set maximum date to today for date of birth
    const today = new Date()
    verifyDobInput.max = today.toISOString().split("T")[0]

    // Set minimum date to 120 years ago
    const minDate = new Date()
    minDate.setFullYear(minDate.getFullYear() - 120)
    verifyDobInput.min = minDate.toISOString().split("T")[0]
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
  console.log("PWD Portal Multi-Step Form loaded successfully!")

  // Show welcome message for first-time visitors
  if (!localStorage.getItem("pwd_portal_visited")) {
    setTimeout(() => {
      showNotification("Welcome to PWD Portal! Book your appointment easily with our new streamlined process.", "info")
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
