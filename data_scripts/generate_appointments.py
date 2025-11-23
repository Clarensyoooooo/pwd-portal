import pymysql
from faker import Faker
import random
import datetime

# --- CONFIGURATION ---
DB_HOST = "localhost"
DB_USER = "root"
DB_PASS = ""
DB_NAME = "pwd_portal"

TOTAL_RECORDS = 50
# We will overload Nov 24 to ensure it says "FULL"
DATE_FULL = datetime.date(2025, 11, 24) 
DATE_OPEN = datetime.date(2025, 11, 26)

# Standard Government Hours (No Lunch Break included in slots usually)
TIME_SLOTS = [
    '09:00:00', 
    '10:00:00', 
    '11:00:00', 
    '13:00:00', 
    '14:00:00', 
    '15:00:00'
]

# --- CONNECT ---
connection = pymysql.connect(
    host=DB_HOST,
    user=DB_USER,
    password=DB_PASS,
    database=DB_NAME,
    charset="utf8mb4",
    cursorclass=pymysql.cursors.DictCursor
)

fake = Faker("en_PH")

try:
    with connection.cursor() as cursor:
        print(f"🚀 Starting generation of {TOTAL_RECORDS} appointments...")

        for i in range(TOTAL_RECORDS):
            # 1. CREATE DUMMY USER FIRST (Required for Appointment)
            # -----------------------------------------------------
            first_name = fake.first_name()
            last_name = fake.last_name()
            # Ensure unique email by adding random number
            email = f"{first_name.lower()}.{last_name.lower()}{random.randint(1000,9999)}@example.com"
            
            # --- FIXED LINE HERE ---
            # We use numerify to guarantee a PH format 09xxxxxxxxx
            phone = fake.numerify('09#########') 
            
            sql_user = """
                INSERT INTO users (
                    first_name, last_name, email, phone, password_hash, 
                    date_of_birth, address, disability_type, is_verified
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            # Dummy hash for 'password123'
            pw_hash = "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi"
            
            user_vals = (
                first_name, last_name, email, phone, pw_hash,
                fake.date_of_birth(minimum_age=18, maximum_age=80),
                fake.address(),
                random.choice(["Visual", "Hearing", "Physical", "Mental"]),
                1
            )
            cursor.execute(sql_user, user_vals)
            user_id = cursor.lastrowid # Get the ID of the user we just made

            # 2. CREATE APPOINTMENT
            # -----------------------------------------------------
            
            # Logic: Overload Nov 24 (First 40 records), Leave Nov 26 open (Last 10)
            if i < 40: 
                appt_date = DATE_FULL
                # Cycle through slots evenly to max ALL of them out
                appt_time = TIME_SLOTS[i % len(TIME_SLOTS)] 
                status = 'confirmed' # Mark them confirmed so they count against the limit
            else:
                appt_date = DATE_OPEN
                appt_time = random.choice(TIME_SLOTS)
                status = 'pending'

            ref_num = f"REF-{random.randint(10000,99999)}-{random.randint(10,99)}"
            
            sql_appt = """
                INSERT INTO appointments (
                    user_id, reference_number, appointment_type, 
                    preferred_date, preferred_time, status, 
                    requirements_submitted, sms_verification_sent
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            appt_vals = (
                user_id,
                ref_num,
                random.choice(['new_application', 'renewal']),
                appt_date,
                appt_time,
                status,
                1, # Requirements submitted
                1  # SMS sent
            )
            
            cursor.execute(sql_appt, appt_vals)

            # Visual feedback every 10 records
            if (i + 1) % 10 == 0:
                print(f"   ... Generated {i + 1} records")

        connection.commit()
        print(f"\n✅ SUCCESS! {TOTAL_RECORDS} appointments created.")
        print(f"📅 Nov 24: Heavily booked (Should show 'Full' or disabled slots)")
        print(f"📅 Nov 26: Lightly booked (Should show available)")

except Exception as e:
    print(f"❌ Error: {e}")
    connection.rollback()

finally:
    if connection.open:
        connection.close()