// Enhanced script.js for PWD Portal Appointment System (No Authentication)

// Global variables
let currentAppointment = null
const currentUserData = null
let currentStep = 1 // From updates
const totalSteps = 4 // From updates

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
  // initializeModals() // From existing, kept here - DELETED due to linting issue

  // Apply date restrictions immediately on page load
  // setDateRestrictions() // From existing, kept here - DELETED due to linting issue

  // Initialize animations
  initializeAnimations() // From existing, kept here

  // Initialize star rating
  // initializeStarRating() // From existing, kept here - DELETED due to linting issue

  // Add accessibility features
  addAccessibilityFeatures() // From existing, kept here

  // Initialize email checking for new applicant form
  const newApplicantEmailInput = document.getElementById("newApplicantEmail")
  if (newApplicantEmailInput) {
    let emailCheckTimeout = null

    newApplicantEmailInput.addEventListener("input", function () {
      clearTimeout(emailCheckTimeout)

      const email = this.value.trim()

      const existingMessage = this.parentElement.querySelector(".email-check-message")
      if (existingMessage) {
        existingMessage.remove()
      }

      if (email && email.includes("@")) {
        emailCheckTimeout = setTimeout(() => {
          checkEmailAvailabilityForNewApplicant(email, this)
        }, 500)
      }
    })
  }

  // Enhanced tracking input with Enter key support
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

// Initialize date restrictions on page load - MOVED FROM EXISTING TO UPDATES
document.addEventListener("DOMContentLoaded", () => {
  // Set date of birth restrictions
  const dobInput = document.getElementById("newDateOfBirth")
  if (dobInput) {
    const today = new Date()
    const maxDate = today.toISOString().split("T")[0]
    const minDate = new Date(today.getFullYear() - 120, today.getMonth(), today.getDate()).toISOString().split("T")[0]

    dobInput.setAttribute("max", maxDate)
    dobInput.setAttribute("min", minDate)
  }
}) // End of MOVED FROM EXISTING TO UPDATES

// Set date restrictions for all date inputs
// function setDateRestrictions() { // DELETED due to redeclaration error
//   const today = new Date()
//   const todayString = today.toISOString().split("T")[0]

//   const tomorrow = new Date(today)
//   tomorrow.setDate(tomorrow.getDate() + 1)
//   const tomorrowString = tomorrow.toISOString().split("T")[0]

//   const maxAppointmentDate = new Date(today)
//   maxAppointmentDate.setDate(maxAppointmentDate.getDate() + 30)
//   const maxAppointmentString = maxAppointmentDate.toISOString().split("T")[0]

//   const minDOB = new Date(today)
//   minDOB.setFullYear(minDOB.getFullYear() - 120)
//   const minDOBString = minDOB.toISOString().split("T")[0]

//   // Date of Birth restrictions
//   const dobInputs = document.querySelectorAll('input[type="date"][id*="DOB"], input[type="date"][id*="Birth"]')
//   dobInputs.forEach((input) => {
//     input.setAttribute("max", todayString)
//     input.setAttribute("min", minDOBString)
//   })

//   // Appointment Date restrictions
//   const appointmentDateInputs = document.querySelectorAll(
//     'input[type="date"][id*="Preferred"], input[type="date"][id*="Date"]:not([id*="Birth"])',
//   )
//   appointmentDateInputs.forEach((input) => {
//     if (!input.id.includes("Birth") && !input.id.includes("DOB")) {
//       input.setAttribute("min", tomorrowString)
//       input.setAttribute("max", maxAppointmentString)
//     }
//   })
// }

// Appointment Functions - MOVED/REPLACED BY UPDATES
/*
function startApplication() {
  document.getElementById("termsModal").style.display = "block"
}

function acceptTerms() {
  const checkbox = document.getElementById("termsCheckbox")

  if (!checkbox.checked) {
    showNotification("Please read and accept the terms and conditions to continue", "error")
    return
  }

  closeModal("termsModal")
  // Show PWD status check modal
  document.getElementById("pwdStatusModal").style.display = "block"
}

function selectPWDStatus(hasID) {
  closeModal("pwdStatusModal")

  if (hasID) {
    // Existing PWD - show email verification
    document.getElementById("existingPWDModal").style.display = "block"
  } else {
    // New applicant - show full registration form
    document.getElementById("newApplicantModal").style.display = "block"
    initializeProgressSteps()
    // Reapply date restrictions when modal opens
    setTimeout(() => setDateRestrictions(), 100)
  }
}

async function verifyExistingPWD(event) {
  event.preventDefault()

  const email = document.getElementById("existingEmail").value.trim()

  if (!email) {
    showNotification("Please enter your email address", "error")
    return
  }

  const submitBtn = event.target.querySelector('button[type="submit"]')
  const originalText = submitBtn.textContent
  submitBtn.textContent = "Verifying..."
  submitBtn.disabled = true

  try {
    const formData = new FormData()
    formData.append("action", "verify_existing_pwd")
    formData.append("email", email)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      // Store user data and show renewal/update form
      currentUserData = result.user
      closeModal("existingPWDModal")
      showRenewalUpdateForm(result.user)
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Verification failed. Please try again.", "error")
  } finally {
    submitBtn.textContent = originalText
    submitBtn.disabled = false
  }
}

function showRenewalUpdateForm(userData) {
  const modal = document.getElementById("renewalUpdateModal")

  // Pre-fill user information
  document.getElementById("renewalName").value = `${userData.first_name} ${userData.last_name}`
  document.getElementById("renewalEmail").value = userData.email
  document.getElementById("renewalPhone").value = userData.phone

  modal.style.display = "block"

  // Apply date restrictions for renewal form
  setTimeout(() => setDateRestrictions(), 100)
}

async function handleRenewalUpdate(event) {
  event.preventDefault()

  const formData = new FormData(event.target)
  formData.append("action", "book_renewal_update")
  formData.append("user_id", currentUserData.id)

  const submitBtn = event.target.querySelector('button[type="submit"]')
  const originalText = submitBtn.textContent
  submitBtn.textContent = "Booking..."
  submitBtn.disabled = true

  try {
    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message, "success")
      closeModal("renewalUpdateModal")

      setTimeout(() => {
        document.getElementById("trackingNumber").value = result.appointment.reference_number
        trackAppointment()
        document.querySelector(".track-appointment").scrollIntoView({
          behavior: "smooth",
        })
      }, 1000)

      event.target.reset()
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Booking failed. Please try again.", "error")
  } finally {
    submitBtn.textContent = originalText
    submitBtn.disabled = false
  }
}
*/
// START OF UPDATED APPLICATION FLOW FUNCTIONS
function startApplication() {
  openModal("termsModal")
}

function acceptTerms() {
  const checkbox = document.getElementById("termsCheckbox")
  if (!checkbox.checked) {
    showNotification("Please accept the Terms and Conditions to continue", "error")
    return
  }
  closeModal("termsModal")
  openModal("pwdStatusModal")
}

function selectPWDStatus(hasPWD) {
  closeModal("pwdStatusModal")
  if (hasPWD) {
    openModal("existingPWDModal")
  } else {
    openModal("newApplicantModal")
    resetForm()
  }
}

async function verifyExistingPWD(event) {
  event.preventDefault()
  const email = document.getElementById("existingEmail").value
  const submitBtn = event.target.querySelector('button[type="submit"]')

  submitBtn.disabled = true
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...'

  try {
    const formData = new FormData()
    formData.append("action", "check_email_detailed")
    formData.append("email", email)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.exists && result.has_pwd_record) {
      // Email verified, show renewal/update form
      document.getElementById("renewalName").value = result.full_name || ""
      document.getElementById("renewalEmail").value = email
      document.getElementById("renewalPhone").value = result.phone || ""

      closeModal("existingPWDModal")
      openModal("renewalUpdateModal")

      // Set date restrictions
      setDateRestrictions("renewalDate") // Note: 'renewalDate' needs to exist in HTML
    } else if (result.exists && result.has_pending) {
      showNotification(
        "You already have a pending appointment. Please wait for it to be processed or contact us to reschedule.",
        "warning",
      )
    } else if (result.exists && !result.has_pwd_record) {
      showNotification("No PWD record found for this email. Please apply as a new applicant.", "error")
    } else {
      showNotification("Email not found in our records. Please apply as a new applicant.", "error")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("An error occurred. Please try again.", "error")
  } finally {
    submitBtn.disabled = false
    submitBtn.innerHTML = "Verify Email"
  }
}

async function handleRenewalUpdate(event) {
  event.preventDefault()
  const formData = new FormData(event.target)
  formData.append("action", "book_appointment") // Assuming backend handles renewal/update via this action
  formData.append("email", document.getElementById("renewalEmail").value)

  const submitBtn = event.target.querySelector('button[type="submit"]')
  submitBtn.disabled = true
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...'

  try {
    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification("Appointment booked successfully! Check your phone for the verification code.", "success")
      closeModal("renewalUpdateModal")

      // Show tracking with reference number
      setTimeout(() => {
        document.getElementById("trackingNumber").value = result.reference_number
        trackAppointment()
      }, 1000)
    } else {
      showNotification(result.message || "Failed to book appointment", "error")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("An error occurred. Please try again.", "error")
  } finally {
    submitBtn.disabled = false
    submitBtn.innerHTML = "Book Appointment"
  }
}
// END OF UPDATED APPLICATION FLOW FUNCTIONS

// Progress-based form for new applicants - MOVED/REPLACED BY UPDATES
/*
let currentStep = 1
const totalSteps = 4

function initializeProgressSteps() {
  currentStep = 1
  updateProgressBar()
  showStep(currentStep)
}

function updateProgressBar() {
  const progress = ((currentStep - 1) / (totalSteps - 1)) * 100
  document.getElementById("progressBar").style.width = `${progress}%`
  document.getElementById("progressText").textContent = `Step ${currentStep} of ${totalSteps}`

  // Update step indicators
  for (let i = 1; i <= totalSteps; i++) {
    const stepIndicator = document.getElementById(`stepIndicator${i}`)
    if (stepIndicator) {
      if (i < currentStep) {
        stepIndicator.className = "step-indicator completed"
      } else if (i === currentStep) {
        stepIndicator.className = "step-indicator active"
      } else {
        stepIndicator.className = "step-indicator"
      }
    }
  }
}

function showStep(step) {
  // Hide all steps
  for (let i = 1; i <= totalSteps; i++) {
    const stepElement = document.getElementById(`step${i}`)
    if (stepElement) {
      stepElement.style.display = "none"
    }
  }

  // Show current step
  const currentStepElement = document.getElementById(`step${step}`)
  if (currentStepElement) {
    currentStepElement.style.display = "block"
  }

  // Update button visibility
  const prevBtn = document.getElementById("prevStepBtn")
  const nextBtn = document.getElementById("nextStepBtn")
  const submitBtn = document.getElementById("submitNewApplicationBtn")

  if (prevBtn) prevBtn.style.display = step === 1 ? "none" : "inline-block"
  if (nextBtn) nextBtn.style.display = step === totalSteps ? "none" : "inline-block"
  if (submitBtn) submitBtn.style.display = step === totalSteps ? "inline-block" : "none"

  // Reapply date restrictions whenever step changes
  setTimeout(() => setDateRestrictions(), 50)
}

async function nextStep() {
  if (await validateStep(currentStep)) {
    if (currentStep < totalSteps) {
      currentStep++
      updateProgressBar()
      showStep(currentStep)

      // Scroll to top of modal
      const modalContent = document.querySelector("#newApplicantModal .modal-content")
      if (modalContent) {
        modalContent.scrollTop = 0
      }
    }
  }
}

function prevStep() {
  if (currentStep > 1) {
    currentStep--
    updateProgressBar()
    showStep(currentStep)

    // Scroll to top of modal
    const modalContent = document.querySelector("#newApplicantModal .modal-content")
    if (modalContent) {
      modalContent.scrollTop = 0
    }
  }
}

async function validateStep(step) {
  const stepElement = document.getElementById(`step${step}`)
  if (!stepElement) return false

  const requiredInputs = stepElement.querySelectorAll("[required]")
  let isValid = true

  // Basic required field validation
  for (const input of requiredInputs) {
    if (!input.value.trim()) {
      input.style.borderColor = "#ef4444"
      isValid = false

      setTimeout(() => {
        input.style.borderColor = ""
      }, 3000)
    } else {
      input.style.borderColor = ""
    }
  }

  // Step 1 specific validation
  if (step === 1) {
    const emailInput = document.getElementById("newApplicantEmail")
    const dobInput = document.getElementById("newApplicantDOB")

    // Validate email format
    if (emailInput && emailInput.value) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      if (!emailRegex.test(emailInput.value)) {
        showNotification("Please enter a valid email address", "error")
        emailInput.style.borderColor = "#ef4444"
        return false
      }

      // Check email availability
      const emailAvailable = await checkEmailAvailabilityForNewApplicant(emailInput.value, emailInput)
      if (!emailAvailable) {
        return false
      }
    }

    // Validate date of birth
    if (dobInput && dobInput.value) {
      const dob = new Date(dobInput.value)
      const today = new Date()
      today.setHours(0, 0, 0, 0)

      const minDate = new Date()
      minDate.setFullYear(minDate.getFullYear() - 120)
      minDate.setHours(0, 0, 0, 0)

      if (dob > today) {
        showNotification("Date of birth cannot be in the future", "error")
        dobInput.style.borderColor = "#ef4444"
        return false
      }

      if (dob < minDate) {
        showNotification("Please enter a valid date of birth (within the last 120 years)", "error")
        dobInput.style.borderColor = "#ef4444"
        return false
      }
    }

    // Validate phone number format
    const phoneInput = document.getElementById("newApplicantPhone")
    if (phoneInput && phoneInput.value) {
      const phoneRegex = /^[0-9]{10,11}$/
      if (!phoneRegex.test(phoneInput.value.replace(/[\s\-()]/g, ""))) {
        showNotification("Please enter a valid 10-11 digit phone number", "error")
        phoneInput.style.borderColor = "#ef4444"
        return false
      }
    }
  }

  // Step 4 specific validation
  if (step === 4) {
    const dateInput = document.getElementById("newApplicantPreferredDate")

    if (dateInput && dateInput.value) {
      const selectedDate = new Date(dateInput.value)
      selectedDate.setHours(0, 0, 0, 0)

      const today = new Date()
      today.setHours(0, 0, 0, 0)

      const tomorrow = new Date(today)
      tomorrow.setDate(tomorrow.getDate() + 1)

      if (selectedDate <= today) {
        showNotification("Appointment date must be at least tomorrow", "error")
        dateInput.style.borderColor = "#ef4444"
        return false
      }

      const maxDate = new Date(today)
      maxDate.setDate(maxDate.getDate() + 30)

      if (selectedDate > maxDate) {
        showNotification("Appointment date cannot be more than 30 days from today", "error")
        dateInput.style.borderColor = "#ef4444"
        return false
      }
    }
  }

  if (!isValid) {
    showNotification("Please fill in all required fields", "error")
  }

  return isValid
}

async function handleNewApplication(event) {
  event.preventDefault()

  if (!(await validateStep(totalSteps))) {
    return
  }

  const formData = new FormData(event.target)
  formData.append("action", "book_new_application")
  formData.append("terms_accepted", "true")

  const submitBtn = document.getElementById("submitNewApplicationBtn")
  const originalText = submitBtn.textContent
  submitBtn.textContent = "Booking..."
  submitBtn.disabled = true

  try {
    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message, "success")
      closeModal("newApplicantModal")

      setTimeout(() => {
        document.getElementById("trackingNumber").value = result.appointment.reference_number
        trackAppointment()
        document.querySelector(".track-appointment").scrollIntoView({
          behavior: "smooth",
        })
      }, 1000)

      event.target.reset()
    } else {
      showNotification(result.error, "error")
    }
  } catch (error) {
    showNotification("Booking failed. Please try again.", "error")
  } finally {
    submitBtn.textContent = originalText
    submitBtn.disabled = false
  }
}
*/
// START OF UPDATED MULTI-STEP FORM FUNCTIONS
function resetForm() {
  currentStep = 1
  document.getElementById("newApplicantForm").reset()
  updateProgressBar()
  showStep(1)
}

function updateProgressBar() {
  const progressFill = document.getElementById("progressBar")
  const progressText = document.getElementById("progressText")
  const percentage = (currentStep / totalSteps) * 100

  progressFill.style.width = percentage + "%"
  progressText.textContent = `Step ${currentStep} of ${totalSteps}`

  // Update step indicators
  for (let i = 1; i <= totalSteps; i++) {
    const indicator = document.getElementById(`stepIndicator${i}`)
    if (indicator) {
      // Check if indicator exists
      if (i < currentStep) {
        indicator.classList.add("completed")
        indicator.classList.remove("active")
      } else if (i === currentStep) {
        indicator.classList.add("active")
        indicator.classList.remove("completed")
      } else {
        indicator.classList.remove("active", "completed")
      }
    }
  }
}

function showStep(step) {
  // Hide all steps
  for (let i = 1; i <= totalSteps; i++) {
    const stepElement = document.getElementById(`step${i}`)
    if (stepElement) {
      stepElement.style.display = "none"
    }
  }

  // Show current step
  const currentStepElement = document.getElementById(`step${step}`)
  if (currentStepElement) {
    currentStepElement.style.display = "block"
  }

  // Update navigation buttons
  const prevBtn = document.getElementById("prevStepBtn")
  const nextBtn = document.getElementById("nextStepBtn")
  const submitBtn = document.getElementById("submitNewApplicationBtn")

  if (step === 1) {
    prevBtn.style.display = "none"
    nextBtn.style.display = "flex"
    submitBtn.style.display = "none"
  } else if (step === totalSteps) {
    prevBtn.style.display = "flex"
    nextBtn.style.display = "none"
    submitBtn.style.display = "flex"

    // Set date restrictions for final step
    setDateRestrictions("newPreferredDate") // Note: 'newPreferredDate' needs to exist in HTML
  } else {
    prevBtn.style.display = "flex"
    nextBtn.style.display = "flex"
    submitBtn.style.display = "none"
  }
}

function validateStep(step) {
  const stepElement = document.getElementById(`step${step}`)
  if (!stepElement) return false // Safety check

  const requiredInputs = stepElement.querySelectorAll("[required]")

  for (const input of requiredInputs) {
    if (!input.value.trim()) {
      input.focus()
      showNotification("Please fill in all required fields", "error")
      return false
    }

    // Validate email format
    if (input.type === "email" && !isValidEmail(input.value)) {
      input.focus()
      showNotification("Please enter a valid email address", "error")
      return false
    }

    // Validate date of birth
    if (input.id === "newDateOfBirth" && !isValidDateOfBirth(input.value)) {
      input.focus()
      showNotification("Please enter a valid date of birth (must be between 0-120 years old)", "error")
      return false
    }
  }

  return true
}

function isValidEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return re.test(email)
}

function isValidDateOfBirth(dateString) {
  const dob = new Date(dateString)
  const today = new Date()
  // Ensure date comparisons are done without time components for accuracy
  const dobOnly = new Date(dob.getFullYear(), dob.getMonth(), dob.getDate())
  const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate())

  const age = Math.floor((todayOnly - dobOnly) / (365.25 * 24 * 60 * 60 * 1000))
  return age >= 0 && age <= 120 && dobOnly <= todayOnly
}

function nextStep() {
  if (validateStep(currentStep)) {
    if (currentStep < totalSteps) {
      // Ensure we don't go beyond totalSteps
      currentStep++
      updateProgressBar()
      showStep(currentStep)

      // Scroll to top of modal
      const modalContent = document.querySelector("#newApplicantModal .modal-content")
      if (modalContent) {
        modalContent.scrollTop = 0
      }
    }
  }
}

function prevStep() {
  if (currentStep > 1) {
    currentStep--
    updateProgressBar()
    showStep(currentStep)

    // Scroll to top of modal
    const modalContent = document.querySelector("#newApplicantModal .modal-content")
    if (modalContent) {
      modalContent.scrollTop = 0
    }
  }
}

function setDateRestrictions(dateInputId) {
  const dateInput = document.getElementById(dateInputId)
  if (!dateInput) return

  const today = new Date()
  const tomorrow = new Date(today)
  tomorrow.setDate(tomorrow.getDate() + 1)

  const maxDate = new Date(today)
  maxDate.setDate(maxDate.getDate() + 30)

  const tomorrowStr = tomorrow.toISOString().split("T")[0]
  const maxDateStr = maxDate.toISOString().split("T")[0]

  dateInput.setAttribute("min", tomorrowStr)
  dateInput.setAttribute("max", maxDateStr)
}

async function handleNewApplication(event) {
  event.preventDefault()

  if (!validateStep(totalSteps)) {
    // Validate the final step before submission
    return
  }

  const formData = new FormData(event.target)
  formData.append("action", "book_appointment") // Assuming backend handles new applications via this action
  // Removed: formData.append('terms_accepted', 'true'); // Assumed to be handled by checkbox value if present

  const submitBtn = document.getElementById("submitNewApplicationBtn")
  submitBtn.disabled = true
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...'

  try {
    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification("Application submitted successfully! Check your phone for the verification code.", "success")
      closeModal("newApplicantModal")

      // Show tracking with reference number
      setTimeout(() => {
        document.getElementById("trackingNumber").value = result.reference_number
        trackAppointment()
      }, 1000)
    } else {
      showNotification(result.message || "Failed to submit application", "error")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("An error occurred. Please try again.", "error")
  } finally {
    submitBtn.disabled = false
    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application'
  }
}
// END OF UPDATED MULTI-STEP FORM FUNCTIONS

// Appointment Functions (tracking, status display) - MOVED/REPLACED BY UPDATES
/*
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
        <span class="detail-value">${appointment.phone}</span>
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
Contact Number: ${currentAppointment.phone}
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
*/
// START OF UPDATED APPOINTMENT TRACKING FUNCTIONS
async function trackAppointment() {
  const referenceNumber = document.getElementById("trackingNumber").value.trim()

  if (!referenceNumber) {
    showNotification("Please enter a reference number", "error")
    return
  }

  const trackBtn = document.querySelector(".btn-track") // Assuming this button exists
  if (!trackBtn) {
    console.error("Track button not found!")
    return
  }
  trackBtn.disabled = true
  trackBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Tracking...'

  try {
    const formData = new FormData()
    formData.append("action", "track_appointment")
    formData.append("reference_number", referenceNumber)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      currentAppointment = result.appointment // Store for other functions like saveAppointmentDetails
      displayAppointmentStatus(result.appointment)
      document.getElementById("noTrackingMessage").style.display = "none"
      document.getElementById("appointmentStatusSection").style.display = "block"
      showNotification("Appointment found!", "success")
    } else {
      showNotification(result.message || "Appointment not found", "error")
      document.getElementById("noTrackingMessage").style.display = "flex"
      document.getElementById("appointmentStatusSection").style.display = "none"
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("An error occurred. Please try again.", "error")
  } finally {
    trackBtn.disabled = false
    trackBtn.innerHTML = "Track"
  }
}

function displayAppointmentStatus(appointment) {
  const statusProgress = document.getElementById("statusProgress")
  const appointmentDetailsCard = document.getElementById("appointmentDetailsCard")
  const smsVerificationSection = document.getElementById("smsVerificationSection")
  const finalConfirmation = document.getElementById("finalConfirmation")
  const appointmentTimeline = document.getElementById("appointmentTimeline")

  // Display status progress
  const statusSteps = [
    { status: "pending", label: "Appointment Booked", desc: "Your appointment has been created" },
    { status: "confirmed", label: "SMS Verified", desc: "Your phone number has been verified" },
    { status: "completed", label: "Appointment Completed", desc: "You attended your appointment" },
    { status: "approved", label: "Application Approved", desc: "Your PWD ID is ready for pickup" },
  ]

  let statusHTML = ""
  // Find the index of the current status to determine which steps are completed
  const currentStatusIndex = statusSteps.findIndex((s) => s.status === appointment.status)

  statusSteps.forEach((step, index) => {
    const isCompleted = index <= currentStatusIndex // Simplified completion logic
    statusHTML += `
            <div class="status-step ${isCompleted ? "completed" : ""}">
                <div class="status-circle">
                    <i class="fas fa-${isCompleted ? "check" : "circle"}"></i>
                </div>
                <div class="status-content">
                    <h4>${step.label}</h4>
                    <p>${step.desc}</p>
                </div>
            </div>
        `
  })

  statusProgress.innerHTML = statusHTML

  // Display appointment details
  appointmentDetailsCard.innerHTML = `
        <div class="details-grid">
            <div class="detail-item">
                <span class="detail-label">Reference Number</span>
                <span class="detail-value">${appointment.reference_number}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value" style="color: ${getStatusColor(appointment.status)}">${formatStatus(appointment.status)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Appointment Type</span>
                <span class="detail-value">${formatAppointmentType(appointment.appointment_type)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Scheduled Date</span>
                <span class="detail-value">${formatDate(appointment.preferred_date)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Scheduled Time</span>
                <span class="detail-value">${formatTime(appointment.preferred_time)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value">${appointment.email}</span>
            </div>
        </div>
    `

  // Show SMS verification if pending and not yet verified
  if (appointment.status === "pending" && !appointment.sms_verified) {
    smsVerificationSection.style.display = "block"
  } else {
    smsVerificationSection.style.display = "none"
  }

  // Show final confirmation if verified (SMS or status is confirmed/completed/approved)
  if (appointment.sms_verified || appointment.status !== "pending") {
    finalConfirmation.style.display = "block"
    finalConfirmation.innerHTML = `
            <div class="confirmation-content">
                <i class="fas fa-check-circle"></i>
                <div class="confirmation-text">
                    <h4>Appointment Confirmed!</h4>
                    <p>Your appointment has been verified and confirmed. Please arrive 15 minutes before your scheduled time and bring all required documents.</p>
                </div>
            </div>
            <div class="confirmation-footer">
                <div>
                    <span class="reference-label">Reference Number:</span>
                    <span class="reference-number">${appointment.reference_number}</span>
                </div>
                <button class="btn-save" onclick="saveAppointmentDetails()">
                    <i class="fas fa-download"></i> Save Details
                </button>
            </div>
        `
  } else {
    finalConfirmation.style.display = "none"
  }

  // Display timeline
  displayTimeline(appointment)
}

function displayTimeline(appointment) {
  const appointmentTimeline = document.getElementById("appointmentTimeline")

  let timelineHTML = '<h4>Appointment Timeline</h4><div class="timeline-items">'

  const events = [
    {
      date: appointment.created_at,
      title: "Appointment Created",
      desc: "Your appointment request was received",
      completed: true,
    },
  ]

  if (appointment.sms_verified_at) {
    events.push({
      date: appointment.sms_verified_at,
      title: "SMS Verified",
      desc: "Your phone number was verified",
      completed: true,
    })
  }

  // Determine scheduled appointment completion based on final status
  const isAppointmentAttendedOrApproved = appointment.status === "completed" || appointment.status === "approved"
  if (appointment.preferred_date) {
    // Only add if a preferred date exists
    events.push({
      date: appointment.preferred_date, // Using preferred_date for the event date
      title: "Scheduled Appointment",
      desc: `Visit our office at ${formatTime(appointment.preferred_time)}`,
      completed: isAppointmentAttendedOrApproved,
    })
  }

  if (appointment.status === "approved") {
    // This event should likely be based on an approval date, if available.
    // For now, using current date as a placeholder.
    events.push({
      date: new Date().toISOString(),
      title: "PWD ID Ready",
      desc: "Your PWD ID is ready for pickup",
      completed: true,
    })
  }

  // Sort events by date just in case
  events.sort((a, b) => new Date(a.date) - new Date(b.date))

  events.forEach((event) => {
    timelineHTML += `
            <div class="timeline-item ${event.completed ? "completed" : ""}">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <h5>${event.title}</h5>
                    <span class="timeline-date">${formatDateTime(event.date)}</span>
                    <p>${event.desc}</p>
                </div>
            </div>
        `
  })

  timelineHTML += "</div>"
  appointmentTimeline.innerHTML = timelineHTML
}

async function verifySMS() {
  const code = document.getElementById("smsVerificationCode").value.trim()
  const referenceNumber = document.getElementById("trackingNumber").value.trim() // Get from tracking input

  if (!code || code.length !== 6) {
    showNotification("Please enter a valid 6-digit code", "error")
    return
  }
  if (!referenceNumber) {
    // Ensure we have a reference number to verify against
    showNotification("Cannot verify SMS without a reference number.", "error")
    return
  }

  const verifyBtn = document.querySelector(".btn-verify") // Assuming this button exists
  if (!verifyBtn) {
    console.error("SMS verify button not found!")
    return
  }
  verifyBtn.disabled = true
  verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...'

  try {
    const formData = new FormData()
    formData.append("action", "verify_sms")
    formData.append("reference_number", referenceNumber)
    formData.append("code", code)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification("SMS verified successfully!", "success")
      // Refresh appointment status to update UI
      trackAppointment() // Re-tracking will fetch the latest status
    } else {
      showNotification(result.message || "Invalid verification code", "error")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("An error occurred. Please try again.", "error")
  } finally {
    verifyBtn.disabled = false
    verifyBtn.innerHTML = "Verify"
  }
}

function saveAppointmentDetails() {
  const referenceNumber = document.getElementById("trackingNumber").value
  // Ensure appointment details are available before trying to save
  if (!currentAppointment) {
    showNotification("No appointment data available to save.", "error")
    return
  }

  // Extract relevant details from currentAppointment object
  const appointmentDetailsText = `
PWD Appointment Details
======================

Reference Number: ${currentAppointment.reference_number}
Status: ${formatStatus(currentAppointment.status)}
Appointment Type: ${formatAppointmentType(currentAppointment.appointment_type)}
Scheduled Date: ${formatDate(currentAppointment.preferred_date)}
Scheduled Time: ${formatTime(currentAppointment.preferred_time)}
Applicant Name: ${currentAppointment.applicant_name || "N/A"}
Email: ${currentAppointment.email || "N/A"}
Contact Number: ${currentAppointment.phone || "N/A"}
Date Submitted: ${formatDate(currentAppointment.created_at)}

Important Reminders:
- Please arrive 15 minutes before your scheduled time.
- Bring all required documents and a valid ID.
- Contact us at 8888-1000 for any concerns or to reschedule.

Generated on: ${new Date().toLocaleString()}
    `

  const blob = new Blob([appointmentDetailsText], { type: "text/plain" })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = `PWD_Appointment_${referenceNumber.replace(/[^a-zA-Z0-9]/g, "_")}.txt` // Sanitize filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)

  showNotification("Appointment details saved successfully!", "success")
}
// END OF UPDATED APPOINTMENT TRACKING FUNCTIONS

// Feedback Functions - MOVED/REPLACED BY UPDATES
/*
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
*/
// START OF UPDATED FEEDBACK HANDLING
async function handleFeedback(event) {
  event.preventDefault()

  const form = event.target
  const formData = new FormData(form)
  formData.append("action", "submit_feedback")

  const submitBtn = form.querySelector('button[type="submit"]')
  submitBtn.disabled = true
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...'

  try {
    const response = await fetch("process_feedback.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      showNotification(result.message || "Thank you for your feedback!", "success")
      form.reset()

      // Reset star rating
      const starInputs = form.querySelectorAll(".star-rating input")
      starInputs.forEach((input) => (input.checked = false))
      // Reset rating text if it exists
      const ratingText = form.querySelector(".rating-text")
      if (ratingText) ratingText.textContent = "Click to rate"
    } else {
      showNotification(result.error || "Failed to submit feedback", "error")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("An error occurred. Please try again.", "error")
  } finally {
    submitBtn.disabled = false
    submitBtn.innerHTML = "Submit Feedback"
  }
}
// END OF UPDATED FEEDBACK HANDLING

// Program Details Functions - MOVED/REPLACED BY UPDATES
/*
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
*/
// Service Details Modal Function - MOVED/REPLACED BY UPDATES
/*
function showServiceDetails(serviceType) {
  const modal = document.getElementById("programModal")
  const title = document.getElementById("programModalTitle")
  const content = document.getElementById("programModalContent")

  const serviceData = {
    health: {
      title: "Kalusugan at Kaayusan (Health & Wellness)",
      icon: "fas fa-heartbeat",
      description: "Comprehensive healthcare access, medical assistance, and wellness programs for PWDs",
      programs: [
        "Free medical consultations and check-ups",
        "Subsidized medications and treatments",
        "Physical and occupational therapy services",
        "Mental health counseling and support",
        "Health insurance enrollment assistance",
        "Medical equipment and assistive devices",
      ],
      partners: [
        { name: "Department of Health", url: "https://doh.gov.ph", icon: "fas fa-hospital" },
        { name: "PhilHealth", url: "https://www.philhealth.gov.ph", icon: "fas fa-medkit" },
        { name: "NCDA Health Programs", url: "https://www.ncda.gov.ph", icon: "fas fa-universal-access" },
      ],
    },
    education: {
      title: "Edukasyon at Pagsasanay (Education & Training)",
      icon: "fas fa-graduation-cap",
      description: "Educational support, scholarships, and skills development programs",
      programs: [
        "Scholarship grants for all levels",
        "Free learning materials and assistive devices",
        "Special education programs and inclusive classrooms",
        "Vocational and technical training",
        "Tutorial and mentoring services",
        "Career guidance and counseling",
      ],
      partners: [
        { name: "Department of Education", url: "https://www.deped.gov.ph", icon: "fas fa-school" },
        { name: "TESDA", url: "https://www.tesda.gov.ph", icon: "fas fa-tools" },
        { name: "CHED", url: "https://ched.gov.ph", icon: "fas fa-university" },
      ],
    },
    livelihood: {
      title: "Kabuhayan at Trabaho (Livelihood & Employment)",
      icon: "fas fa-briefcase",
      description: "Job placement, business opportunities, and entrepreneurship support",
      programs: [
        "Skills training and capacity building",
        "Job placement and employment assistance",
        "Business startup loans and grants",
        "Entrepreneurship training programs",
        "Cooperative formation support",
        "Workplace accessibility consultation",
      ],
      partners: [
        { name: "DOLE", url: "https://www.dole.gov.ph", icon: "fas fa-briefcase" },
        { name: "TESDA", url: "https://www.tesda.gov.ph", icon: "fas fa-tools" },
        { name: "DTI", url: "https://www.dti.gov.ph", icon: "fas fa-store" },
      ],
    },
    mobility: {
      title: "Accessibility at Mobilidad (Accessibility & Mobility)",
      icon: "fas fa-wheelchair",
      description: "Mobility aids, assistive devices, and accessibility modifications",
      programs: [
        "Free wheelchair and mobility aid provision",
        "Assistive devices and equipment",
        "Transportation assistance programs",
        "Home accessibility modifications",
        "Public infrastructure accessibility advocacy",
        "Equipment maintenance and repair",
      ],
      partners: [
        { name: "NCDA", url: "https://www.ncda.gov.ph", icon: "fas fa-universal-access" },
        { name: "DSWD", url: "https://www.dswd.gov.ph", icon: "fas fa-hands-helping" },
        { name: "DOTr", url: "https://dotr.gov.ph", icon: "fas fa-bus" },
      ],
    },
    protection: {
      title: "Social Protection (Proteksyon at Benepisyo)",
      icon: "fas fa-shield-alt",
      description: "Government benefits, insurance programs, and social security support",
      programs: [
        "Social security and insurance enrollment",
        "Pension and retirement benefits",
        "Emergency financial assistance",
        "Legal aid and advocacy services",
        "Social amelioration programs",
        "Disaster preparedness and response",
      ],
      partners: [
        { name: "SSS", url: "https://www.sss.gov.ph", icon: "fas fa-shield-alt" },
        { name: "GSIS", url: "https://www.gsis.gov.ph", icon: "fas fa-building" },
        { name: "DSWD", url: "https://www.dswd.gov.ph", icon: "fas fa-hands-helping" },
      ],
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
*/
// START OF UPDATED SERVICE DETAILS MODAL
function showServiceDetails(service) {
  const modal = document.getElementById("programModal")
  const title = document.getElementById("programModalTitle")
  const content = document.getElementById("programModalContent")

  const serviceDetails = {
    health: {
      icon: "fa-heartbeat",
      title: "Kalusugan at Kaayusan (Health & Wellness)",
      description: "Comprehensive healthcare programs and medical assistance for PWDs",
      programs: [
        "Free medical consultations and check-ups",
        "Medicine assistance program",
        "Physical therapy and rehabilitation services",
        "Mental health support and counseling",
        "Health insurance facilitation",
        "Wellness and nutrition programs",
      ],
      partners: [
        { name: "Department of Health", url: "https://doh.gov.ph" },
        { name: "PhilHealth", url: "https://www.philhealth.gov.ph" },
      ],
    },
    education: {
      icon: "fa-graduation-cap",
      title: "Edukasyon at Pagsasanay (Education & Training)",
      description: "Educational support and skills development programs for PWDs",
      programs: [
        "Scholarship programs for PWD students",
        "Special education (SPED) support",
        "Skills training and vocational courses",
        "Inclusive education advocacy",
        "Educational materials and assistive technology",
        "Tutoring and mentoring programs",
      ],
      partners: [
        { name: "Department of Education", url: "https://www.deped.gov.ph" },
        { name: "TESDA", url: "https://www.tesda.gov.ph" },
      ],
    },
    livelihood: {
      icon: "fa-briefcase",
      title: "Kabuhayan at Trabaho (Livelihood & Employment)",
      description: "Job placement and entrepreneurship support for PWDs",
      programs: [
        "Job matching and placement services",
        "Livelihood assistance and capital support",
        "Entrepreneurship training",
        "Workplace accommodation support",
        "Career counseling and guidance",
        "Business development seminars",
      ],
      partners: [
        { name: "DOLE", url: "https://www.dole.gov.ph" },
        { name: "DTI", url: "https://www.dti.gov.ph" },
      ],
    },
    mobility: {
      icon: "fa-wheelchair",
      title: "Accessibility at Mobilidad (Accessibility & Mobility)",
      description: "Mobility aids and accessibility support for PWDs",
      programs: [
        "Assistive devices provision (wheelchairs, canes, etc.)",
        "Home modification assistance",
        "Transportation support programs",
        "Accessibility audit and consultation",
        "Prosthetics and orthotics services",
        "Mobility training programs",
      ],
      partners: [
        { name: "DSWD", url: "https://www.dswd.gov.ph" },
        { name: "NCDA", url: "https://www.ncda.gov.ph" },
      ],
    },
    protection: {
      icon: "fa-shield-alt",
      title: "Social Protection (Proteksyon at Benepisyo)",
      description: "Government benefits and social security programs for PWDs",
      programs: [
        "Social pension for indigent PWDs",
        "PhilHealth coverage and benefits",
        "SSS/GSIS disability benefits",
        "Emergency assistance programs",
        "Food and nutrition support",
        "Housing assistance",
      ],
      partners: [
        { name: "DSWD", url: "https://www.dswd.gov.ph" },
        { name: "SSS", url: "https://www.sss.gov.ph" },
        { name: "GSIS", url: "https://www.gsis.gov.ph" },
      ],
    },
    community: {
      icon: "fa-users",
      title: "Pakikilahok sa Komunidad (Community Participation)",
      description: "Social integration and community engagement programs",
      programs: [
        "Sports and recreation programs",
        "Arts and culture activities",
        "Community organizing and advocacy",
        "Peer support groups",
        "Leadership training",
        "Social and civic engagement activities",
      ],
      partners: [
        { name: "NCDA", url: "https://www.ncda.gov.ph" },
        { name: "Department of Tourism", url: "https://www.tourism.gov.ph" },
      ],
    },
  }

  const details = serviceDetails[service]
  if (!details) return

  title.textContent = details.title

  let partnersHTML = '<div class="partner-links-grid">'
  details.partners.forEach((partner) => {
    partnersHTML += `
            <a href="${partner.url}" target="_blank" rel="noopener noreferrer" class="partner-link-card">
                <i class="fas fa-external-link-alt"></i>
                <span>${partner.name}</span>
                <i class="fas fa-chevron-right"></i>
            </a>
        `
  })
  partnersHTML += "</div>"

  content.innerHTML = `
        <div class="program-detail-content">
            <div class="program-header">
                <i class="fas ${details.icon}"></i>
                <p class="program-description">${details.description}</p>
            </div>
            
            <div class="program-section">
                <h4><i class="fas fa-list-ul"></i> Available Programs & Services</h4>
                <ul class="program-list">
                    ${details.programs.map((program) => `<li>${program}</li>`).join("")}
                </ul>
            </div>

            <div class="program-section">
                <h4><i class="fas fa-handshake"></i> Partner Organizations</h4>
                ${partnersHTML}
            </div>

            <div class="program-actions">
                <button class="btn-primary" onclick="startApplication()">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </button>
                <button class="btn-secondary" onclick="closeModal('programModal')">
                    Close
                </button>
            </div>
        </div>
    `

  openModal("programModal")
}
// END OF UPDATED SERVICE DETAILS MODAL

// FAQ toggle function - MOVED/REPLACED BY UPDATES
/*
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
*/
// START OF UPDATED FAQ TOGGLE
function toggleFAQ(element) {
  const answer = element.nextElementSibling
  const icon = element.querySelector("i")

  answer.classList.toggle("active")

  if (answer.classList.contains("active")) {
    icon.style.transform = "rotate(180deg)"
  } else {
    icon.style.transform = "rotate(0deg)"
  }
}
// END OF UPDATED FAQ TOGGLE

// Utility functions - MOVED/REPLACED BY UPDATES
/*
function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none"
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
*/
// START OF UPDATED UTILITY FUNCTIONS
function jsonResponse(data, status = 200) {
  return {
    status: status,
    data: data,
  }
}

function showNotification(message, type = "success") {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(".notification")
  existingNotifications.forEach((notif) => notif.remove())

  // Create notification element
  const notification = document.createElement("div")
  notification.className = `notification notification-${type}`
  notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${type === "success" ? "#10b981" : type === "error" ? "#ef4444" : type === "warning" ? "#f59e0b" : "#2c5aa0"}; /* Fallback to blue for info */
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        z-index: 10001; /* Higher z-index than modals */
        min-width: 300px;
        max-width: 500px;
        animation: slideInRight 0.3s ease;
    `

  notification.innerHTML = `
        <div class="notification-content">
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `

  document.body.appendChild(notification)

  // Auto remove after 5 seconds
  setTimeout(() => {
    notification.style.animation = "slideOutRight 0.3s ease"
    setTimeout(() => notification.remove(), 300)
  }, 5000)
}

// Add animation keyframes for notifications
const style = document.createElement("style")
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`
document.head.appendChild(style)

// Modal Functions
function openModal(modalId) {
  const modal = document.getElementById(modalId)
  if (modal) {
    modal.style.display = "block"
    document.body.style.overflow = "hidden" // Prevent scrolling behind modal
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId)
  if (modal) {
    modal.style.display = "none"
    document.body.style.overflow = "auto" // Restore scrolling
  }
}

// Close modal when clicking outside
window.onclick = (event) => {
  // Check if the clicked element is the modal background itself
  const modals = document.querySelectorAll(".modal")
  modals.forEach((modal) => {
    if (event.target === modal) {
      modal.style.display = "none"
      document.body.style.overflow = "auto" // Restore scrolling
    }
  })
}

// Utility formatting functions
function formatStatus(status) {
  const statusMap = {
    pending: "Pending",
    confirmed: "Confirmed",
    completed: "Completed",
    approved: "Approved",
    cancelled: "Cancelled",
  }
  return statusMap[status] || status
}

function getStatusColor(status) {
  const colorMap = {
    pending: "#f59e0b",
    confirmed: "#3b82f6",
    completed: "#10b981",
    approved: "#10b981",
    cancelled: "#ef4444",
  }
  return colorMap[status] || "#666" // Default color
}

function formatAppointmentType(type) {
  const typeMap = {
    new_application: "New Application",
    renewal: "PWD ID Renewal",
    update: "Update Information",
  }
  return typeMap[type] || type
}

function formatDate(dateString) {
  if (!dateString) return "N/A"
  const options = { year: "numeric", month: "long", day: "numeric" }
  try {
    return new Date(dateString).toLocaleDateString("en-US", options)
  } catch (e) {
    console.error("Error formatting date:", dateString, e)
    return "Invalid Date"
  }
}

function formatTime(timeString) {
  if (!timeString) return "N/A"
  const [hours, minutes] = timeString.split(":")
  const hour = Number.parseInt(hours)
  const ampm = hour >= 12 ? "PM" : "AM"
  const displayHour = hour % 12 || 12 // Convert to 12-hour format
  return `${displayHour}:${minutes} ${ampm}`
}

function formatDateTime(dateTimeString) {
  if (!dateTimeString) return "N/A"
  try {
    const date = new Date(dateTimeString)
    return date.toLocaleString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    })
  } catch (e) {
    console.error("Error formatting datetime:", dateTimeString, e)
    return "Invalid DateTime"
  }
}
// END OF UPDATED UTILITY FUNCTIONS

// Initialize animations
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
    // Calculate element's position relative to the viewport
    const elementTop = element.getBoundingClientRect().top + window.scrollY
    const elementBottom = elementTop + element.offsetHeight
    const viewportTop = window.scrollY
    const viewportBottom = viewportTop + windowHeight

    // Check if the element is at least partially in the viewport
    // Adjust offset if needed, e.g., elementBottom > viewportTop + 100 for triggering a bit earlier
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

// Initialize everything when page loads
window.addEventListener("load", () => {
  // Add loaded class to body for CSS animations
  document.body.classList.add("loaded")

  // Initialize any additional features
  console.log("PWD Portal (No Authentication) loaded successfully!")

  // Show welcome message for first-time visitors
  if (!localStorage.getItem("pwd_portal_visited")) {
    setTimeout(() => {
      showNotification("Welcome to PWD Portal! Book your appointment easily without registration.", "info")
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
        document.body.style.overflow = "auto" // Ensure scrolling is restored
      }
    })
  }
})

async function checkEmailAvailabilityForNewApplicant(email, inputElement) {
  try {
    const formData = new FormData()
    formData.append("action", "check_email_detailed") // Using the same action as in verifyExistingPWD update
    formData.append("email", email)

    const response = await fetch("appointments.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    const existingMessage = inputElement.parentElement.querySelector(".email-check-message")
    if (existingMessage) {
      existingMessage.remove()
    }

    if (!result.available) {
      const message = document.createElement("div")
      message.className = "email-check-message email-unavailable"

      let messageText = ""
      if (result.reason === "pwd_exists") {
        messageText = `<i class="fas fa-info-circle"></i>
        This email is already registered in our PWD records. Please select "Yes, I have a PWD ID" to book a renewal or update appointment.`
      } else if (result.reason === "pending_appointment") {
        messageText = `<i class="fas fa-exclamation-circle"></i>
        This email already has a ${result.appointment.status} appointment (Ref: ${result.appointment.reference_number}). 
        Please complete or cancel it before booking a new one.`
      } else {
        // Generic message if reason is not specified or unknown
        messageText = `<i class="fas fa-exclamation-circle"></i> This email is already in use or has an existing appointment.`
      }

      message.innerHTML = messageText
      inputElement.parentElement.appendChild(message)
      inputElement.style.borderColor = "#ef4444"
      return false
    } else {
      // Email is available
      inputElement.style.borderColor = "#10b981" // Green border for availability
      return true
    }
  } catch (error) {
    console.error("Error checking email:", error)
    // Assume email is available if an error occurs to avoid blocking user
    inputElement.style.borderColor = "#f59e0b" // Yellow border for potential issue
    showNotification("Could not verify email availability. Please proceed with caution.", "warning")
    return true
  }
}
