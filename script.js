// Enhanced script.js for PWD Portal Appointment System (No Authentication)

// Global variables
let currentAppointment = null
let currentUserData = null

// Helper function to check for weekends
function isWeekend(date) {
  const day = date.getDay();
  return day === 0 || day === 6; // 0 = Sunday, 6 = Saturday
}

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

  // Initialize animations
  initializeAnimations()

  // Initialize star rating
  initializeStarRating()

  // Add accessibility features
  addAccessibilityFeatures()

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

// Program Management Functions
let allPrograms = []
let selectedProgramId = null

async function loadPublicPrograms() {
  try {
    const response = await fetch("admin/api/programs.php?action=get_programs")
    const result = await response.json()

    if (result.success) {
      allPrograms = result.programs
      displayPublicProgramsList(allPrograms)
    } else {
      console.error("Error loading programs:", result.error)
      document.getElementById("availableProgramsList").innerHTML = '<div class="loading">Error loading programs</div>'
    }
  } catch (error) {
    console.error("Error loading programs:", error)
    document.getElementById("availableProgramsList").innerHTML = '<div class="loading">Error loading programs</div>'
  }
}

function displayPublicProgramsList(programs) {
  const listContainer = document.getElementById("availableProgramsList")

  if (programs.length === 0) {
    listContainer.innerHTML = '<div class="loading">No programs available at this time</div>'
    return
  }

  listContainer.innerHTML = programs
    .map(
      (program) => `
        <div class="program-list-item ${selectedProgramId === program.id ? "active" : ""}" onclick="selectProgram(${program.id})">
            <div class="program-list-item-title">${program.title}</div>
            <div class="program-list-item-category">${program.category}</div>
        </div>
    `,
    )
    .join("")
}

function selectProgram(programId) {
  selectedProgramId = programId
  const program = allPrograms.find((p) => p.id === programId)

  if (!program) return

  // Update active state
  displayPublicProgramsList(allPrograms)

  // Show form and populate it
  document.getElementById("selectedProgramId").value = programId
  document.getElementById("selectedProgramTitle").textContent = program.title
  // ADD THIS LINE BELOW
  document.getElementById("selectedProgramDescription").textContent = program.description

  // Display requirements
  const requirementsDiv = document.getElementById("programRequirements")
  const requirementsArray = program.requirements.split("|")

  requirementsDiv.innerHTML = `
        <div class="program-requirements-info">
            <h4><i class="fas fa-clipboard-list"></i> Program Requirements</h4>
            <ul>
                ${requirementsArray.map((req) => `<li>${req.trim()}</li>`).join("")}
            </ul>
        </div>
    `

  // Show form, hide prompt
  document.getElementById("programSelectionPrompt").style.display = "none"
  document.getElementById("programApplicationForm").style.display = "block"

  // Clear form
  document.getElementById("applyProgramForm").reset()
}

function clearProgramSelection() {
  selectedProgramId = null
  document.getElementById("programSelectionPrompt").style.display = "block"
  document.getElementById("programApplicationForm").style.display = "none"
  displayPublicProgramsList(allPrograms)
}

/**
 * A simple regex check for email format, consistent with your site's other validation.
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Using the regex from your validateStep function
    return regex.test(email);
}

/**
 * Displays an error message for a specific form field.
 * It uses the .input-error and .input-error-message classes from your index.php.
 */
function showProgramFormError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;

    // Add error class to the input/select/textarea
    input.classList.add('input-error');

    // Find the parent .form-group
    const formGroup = input.closest('.form-group');
    if (!formGroup) return;

    // Create and append the error message span
    const errorSpan = document.createElement('span');
    errorSpan.className = 'input-error-message';
    errorSpan.textContent = message;
    formGroup.appendChild(errorSpan);
}

/**
 * Clears all validation errors from the program application form.
 */
function clearProgramFormErrors() {
    const form = document.getElementById('applyProgramForm');

    // Remove all error classes from inputs
    form.querySelectorAll('.input-error').forEach(input => {
        input.classList.remove('input-error');
    });

    // Remove all error message spans
    form.querySelectorAll('.input-error-message').forEach(span => {
        span.remove();
    });
}

/**
 * Validates all fields in the program application form.
 * Returns true if all fields are valid, false otherwise.
 */
function validateProgramForm() {
    clearProgramFormErrors(); // Clear all previous errors
    let isValid = true;
    
    // Get all the form inputs
    const firstName = document.getElementById('appFirstName');
    const lastName = document.getElementById('appLastName');
    const email = document.getElementById('appEmail');
    const phone = document.getElementById('appPhone');
    const dob = document.getElementById('appDOB');
    const disability = document.getElementById('appDisability');
    const address = document.getElementById('appAddress');

    // 1. Check First Name
    if (firstName.value.trim() === '') {
        showProgramFormError('appFirstName', 'First Name is required.');
        isValid = false;
    }

    // 2. Check Last Name
    if (lastName.value.trim() === '') {
        showProgramFormError('appLastName', 'Last Name is required.');
        isValid = false;
    }

    // 3. Check Email
    if (email.value.trim() === '') {
        showProgramFormError('appEmail', 'Email is required.');
        isValid = false;
    } else if (!isValidEmail(email.value.trim())) {
        showProgramFormError('appEmail', 'Please enter a valid email address.');
        isValid = false;
    }

    // 4. Check Phone (using regex to match placeholder: +63 912 345 6789)
    const phoneRegex = /^\+63\s9\d{2}\s\d{3}\s\d{4}$/;
    if (phone.value.trim() === '') {
        showProgramFormError('appPhone', 'Phone is required.');
        isValid = false;
    } else if (!phoneRegex.test(phone.value.trim())) {
        showProgramFormError('appPhone', 'Phone must be in the format +63 912 345 6789.');
        isValid = false;
    }

    // 5. Check Date of Birth
    if (dob.value.trim() === '') {
        showProgramFormError('appDOB', 'Date of Birth is required.');
        isValid = false;
    } else if (new Date(dob.value) > new Date()) {
        // Check if the date is in the future
        showProgramFormError('appDOB', 'Date of Birth cannot be in the future.');
        isValid = false;
    }

    // 6. Check Disability Type
    if (disability.value === '') {
        showProgramFormError('appDisability', 'Please select a disability type.');
        isValid = false;
    }
    
    // 7. Check Address
    if (address.value.trim() === '') {
        showProgramFormError('appAddress', 'Complete Address is required.');
        isValid = false;
    }

    return isValid;
}

async function handleProgramApplication(event) {
    event.preventDefault(); // Stop the form from submitting immediately

    if (!selectedProgramId) {
        showNotification("Please select a program", "error");
        return;
    }

    // 1. Run the new validation function
    if (!validateProgramForm()) {
        showNotification('Please fix the errors in the form.', 'error');
        return;
    }

    // 2. If validation is successful, proceed with submission
    const form = document.getElementById('applyProgramForm');
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;

    try {
        // Disable button and show a loading state
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        const formData = new FormData(form);
        formData.append("action", "submit_program_application"); // This action is from your original script

        // This API path is from your original script
        const response = await fetch("admin/api/programs.php", { 
            method: "POST",
            body: formData,
        });

        const result = await response.json();

        if (result.success) {
            showNotification("Application submitted successfully! We will contact you shortly.", "success");
            clearProgramSelection();
            form.reset();
            clearProgramFormErrors(); // Clear errors on success
        } else {
            showNotification(result.error || "Error submitting application", "error");
        }
    } catch (error) {
        console.error("Error submitting application:", error);
        showNotification("Error submitting application", "error");
    } finally {
        // Re-enable the button
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    }
}

// Load programs on page load
document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("availableProgramsList")) {
    loadPublicPrograms()
  }
})

// Appointment Functions
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
    // Apply date restrictions immediately when modal opens
    applyDateRestrictions()
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
  applyDateRestrictionsForRenewal()
}

function applyDateRestrictionsForRenewal() {
  const dateInput = document.getElementById("renewalPreferredDate")

  if (dateInput) {
    const tomorrow = new Date()
    tomorrow.setDate(tomorrow.getDate() + 1)
    dateInput.min = tomorrow.toISOString().split("T")[0]

    const maxDate = new Date()
    maxDate.setMonth(maxDate.getMonth() + 2) // Allow booking up to 2 months in advance
    dateInput.max = maxDate.toISOString().split("T")[0]

    // Add real-time validation for weekends
    dateInput.addEventListener('input', function() {
        // Add T00:00:00 to handle timezone differences correctly
        const selectedDate = new Date(this.value + 'T00:00:00'); 
        if (isWeekend(selectedDate)) {
            showNotification('Appointments are not available on weekends. Please select a weekday.', 'error');
            this.style.borderColor = "#ef4444";
            this.value = ''; // Clear invalid selection
        } else {
            this.style.borderColor = "";
        }
    });
  }
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

// Progress-based form for new applicants
let currentStep = 1
const totalSteps = 4

function initializeProgressSteps() {
  currentStep = 1
  updateProgressBar()
  showStep(currentStep)
}

function applyDateRestrictions() {
  // Apply restrictions to date of birth (Step 1)
  const dobInput = document.getElementById("newApplicantDOB");
  if (dobInput) {
    const today = new Date();
    dobInput.max = today.toISOString().split("T")[0];

    const minDate = new Date();
    minDate.setFullYear(minDate.getFullYear() - 120);
    dobInput.min = minDate.toISOString().split("T")[0];

    // **FIX**: Validate date of birth on blur (when user leaves the input) to avoid premature validation.
    dobInput.addEventListener('blur', function() {
        // Do nothing if the input is empty
        if (!this.value) {
            this.style.borderColor = "";
            return;
        }

        const dob = new Date(this.value);

        // Check if the entered value is a valid date. Prevents errors on incomplete input.
        if (isNaN(dob.getTime())) {
            showNotification('Please enter a valid and complete date of birth.', 'error');
            this.style.borderColor = "#ef4444";
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const minValidDate = new Date();
        minValidDate.setFullYear(minValidDate.getFullYear() - 120);
        minValidDate.setHours(0, 0, 0, 0);

        if (dob > today) {
            showNotification('Date of birth cannot be in the future.', 'error');
            this.style.borderColor = "#ef4444";
            this.value = ''; // Clear the invalid future date
        } else if (dob < minValidDate) {
            showNotification('Please enter a valid date of birth (not more than 120 years ago).', 'error');
            this.style.borderColor = "#ef4444";
            this.value = ''; // Clear the invalid past date
        } else {
            this.style.borderColor = ""; // Valid date
        }
    });
  }

  // Apply restrictions to appointment date (Step 4)
  const dateInput = document.getElementById("newApplicantPreferredDate");
  if (dateInput) {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.min = tomorrow.toISOString().split("T")[0];

    const maxDate = new Date();
    maxDate.setMonth(maxDate.getMonth() + 2); // Allow booking up to 2 months in advance
    dateInput.max = maxDate.toISOString().split("T")[0];
    
     // Add real-time validation for weekends
    dateInput.addEventListener('input', function() {
        // Add T00:00:00 to handle timezone differences correctly
        const selectedDate = new Date(this.value + 'T00:00:00');
        if (isWeekend(selectedDate)) {
            showNotification('Appointments are not available on weekends. Please select a weekday.', 'error');
            this.style.borderColor = "#ef4444";
            this.value = ''; // Clear invalid selection
        } else {
            this.style.borderColor = "";
        }
    });
  }
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

  // Reapply date restrictions whenever step changes (ensures calendar is properly restricted)
  if (step === 1 || step === 4) {
    applyDateRestrictions();
  }
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
  const stepElement = document.getElementById(`step${step}`);
  if (!stepElement) return false;

  // --- Start of new, prioritized validation logic ---

  // Step 1: Perform specific format/value validations first.
  if (step === 1) {
    const emailInput = document.getElementById("newApplicantEmail");
    const dobInput = document.getElementById("newApplicantDOB");
    const phoneInput = document.getElementById("newApplicantPhone");

    // Validate email format
    if (emailInput && emailInput.value) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(emailInput.value)) {
        showNotification("Please enter a valid email address", "error");
        emailInput.style.borderColor = "#ef4444";
        return false;
      }
      // Check email availability
      const emailAvailable = await checkEmailAvailabilityForNewApplicant(emailInput.value, emailInput);
      if (!emailAvailable) {
        return false;
      }
    }

    // Validate date of birth
    if (dobInput && dobInput.value) {
      const dob = new Date(dobInput.value);
      if (isNaN(dob.getTime())) {
        showNotification("Please enter a valid and complete date of birth", "error");
        dobInput.style.borderColor = "#ef4444";
        return false;
      }
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const minDate = new Date();
      minDate.setFullYear(minDate.getFullYear() - 120);
      minDate.setHours(0, 0, 0, 0);
      if (dob > today) {
        showNotification("Date of birth cannot be in the future", "error");
        dobInput.style.borderColor = "#ef4444";
        return false;
      }
      if (dob < minDate) {
        showNotification("Please enter a valid date of birth", "error");
        dobInput.style.borderColor = "#ef4444";
        return false;
      }
    }

    // Validate phone number format
    if (phoneInput && phoneInput.value) {
      const phoneRegex = /^[0-9]{10,11}$/;
      if (!phoneRegex.test(phoneInput.value.replace(/[\s\-()]/g, ""))) {
        showNotification("Please enter a valid 10-11 digit phone number", "error");
        phoneInput.style.borderColor = "#ef4444";
        return false;
      }
    }
  }

  // Step 4: Specific validation for preferred date
  if (step === 4) {
    const dateInput = document.getElementById("newApplicantPreferredDate");
    if (dateInput && dateInput.value) {
      const selectedDate = new Date(dateInput.value + 'T00:00:00');
      selectedDate.setHours(0, 0, 0, 0);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      if (selectedDate <= today) {
        showNotification("Appointment date must be in the future", "error");
        dateInput.style.borderColor = "#ef4444";
        return false;
      }
      if (isWeekend(selectedDate)) {
        showNotification("Appointments are not available on weekends. Please select a weekday.", "error");
        dateInput.style.borderColor = "#ef4444";
        return false;
      }
      const maxDate = new Date(today);
      maxDate.setMonth(maxDate.getMonth() + 2);
      if (selectedDate > maxDate) {
        showNotification("Appointment date cannot be more than 2 months from today", "error");
        dateInput.style.borderColor = "#ef4444";
        return false;
      }
    }
  }

  // Step 2: If all specific validations passed, now check for empty required fields.
  const requiredInputs = stepElement.querySelectorAll("[required]");
  let allFieldsFilled = true;
  for (const input of requiredInputs) {
    if (!input.value.trim()) {
      input.style.borderColor = "#ef4444";
      allFieldsFilled = false;
      setTimeout(() => {
        input.style.borderColor = "";
      }, 3000);
    } else {
      // Clear border if it was previously marked as invalid but is now filled
      input.style.borderColor = "";
    }
  }

  if (!allFieldsFilled) {
    showNotification("Please fill in all required fields", "error");
    return false;
  }

  // --- End of new logic ---
  return true; // If we reached here, the step is valid.
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
  if (!timeString) return '';
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
      }
    })
  }
})

async function checkEmailAvailabilityForNewApplicant(email, inputElement) {
  try {
    const formData = new FormData()
    formData.append("action", "check_email_detailed")
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
      }

      message.innerHTML = messageText
      inputElement.parentElement.appendChild(message)
      inputElement.style.borderColor = "#ef4444"
      return false
    } else {
      inputElement.style.borderColor = "#10b981"
      return true
    }
  } catch (error) {
    console.error("Error checking email:", error)
    return true
  }
}
