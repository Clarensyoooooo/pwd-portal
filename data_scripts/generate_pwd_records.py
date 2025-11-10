import pymysql
from faker import Faker
import random
import json
from shapely.geometry import shape, Point
import datetime
from datetime import date # Import date for age calculation

# Database connection settings (adjust if needed)
connection = pymysql.connect(
    host="localhost",
    user="root",
    password="",
    database="pwd_portal",
    charset="utf8mb4",
    cursorclass=pymysql.cursors.DictCursor
)

# Use Filipino localization for names
fake = Faker("en_PH")

# Barangay names and counts
barangay_counts = {
    "Barangay 1": 42, 
    "Barangay 2": 83, 
    "Barangay 3": 47, 
    "Barangay 4": 84,
    "San Agustin": 52, 
    "San Antonio": 344, 
    "San Bartolome": 159, 
    "San Felix": 113,
    "San Fernando": 32, 
    "San Francisco": 92, 
    "San Isidro Norte": 61, 
    "San Isidro Sur": 65,
    "San Joaquin": 72, 
    "San Jose": 75, 
    "San Juan": 75, 
    "San Luis": 64, 
    "San Miguel": 358,
    "San Pablo": 159, 
    "San Pedro": 164, 
    "San Rafael": 234, 
    "San Roque": 272,
    "San Vicente": 481, 
    "Santa Ana": 44, 
    "Santa Anastacia": 214, 
    "Santa Clara": 86,
    "Santa Cruz": 41, 
    "Santa Elena": 38, 
    "Santa Maria": 333, 
    "Santiago": 135,
    "Santa Teresita": 71
}

# --- *** DIVERSIFICATION START *** ---

# Weighted choices for Gender
genders = ["Female", "Male"]
gender_weights = [0.50, 0.48] 

# Weighted choices for Civil Status
civil_statuses = ['Single', 'Married', 'Widowed', 'Separated']
civil_status_weights = [0.40, 0.45, 0.10, 0.05] 

# Weighted choices for Disability Type
disability_types = [
    "Physical Disability", "Visual Impairment", "Hearing Impairment",
    "Intellectual Disability", "Psychosocial Disability", "Multiple Disabilities"
]
disability_weights = [0.30, 0.20, 0.15, 0.15, 0.10, 0.10] 

# Weighted choices for Employment Status
employment_statuses = ['Unemployed', 'Employed', 'Self-employed', 'Student', 'Retired']
employment_weights = [0.40, 0.25, 0.15, 0.10, 0.10] 

# --- *** START OF FIX 1 *** ---
# Weighted choices for Record Status (ID Status)
# Replaced 'pending_validation' with 'draft' to match your application's logic.
# Added 'expired' which will be handled by date logic below.
record_statuses = ['validated', 'issued', 'draft', 'inactive', 'expired']
# NEW WEIGHTS: 3% validated, 50% issued (active), 3% draft, 20% inactive, 24% expired
record_status_weights = [0.03, 0.50, 0.03, 0.20, 0.24]
# --- *** END OF FIX 1 *** ---

# Weighted Age Ranges (min_age, max_age)
age_ranges = [(1, 17), (18, 30), (31, 45), (46, 60), (61, 90)]
age_weights = [0.15, 0.25, 0.30, 0.20, 0.10] 

def get_weighted_dob():
    chosen_range = random.choices(age_ranges, weights=age_weights, k=1)[0]
    min_age, max_age = chosen_range
    today = date.today()
    latest_birth_year = today.year - min_age
    earliest_birth_year = today.year - max_age

    earliest_birth_year = max(earliest_birth_year, today.year - 95) 
    if earliest_birth_year > latest_birth_year:
        earliest_birth_year = latest_birth_year

    try:
        start_date = date(earliest_birth_year, today.month, today.day)
    except ValueError: 
         start_date = date(earliest_birth_year, today.month, today.day -1)

    try:
       end_date = date(latest_birth_year, today.month, today.day)
    except ValueError:
       end_date = date(latest_birth_year, today.month, today.day -1)

    if start_date > end_date:
        start_date = end_date

    return fake.date_between(start_date=start_date, end_date=end_date)

# --- *** DIVERSIFICATION END *** ---


# Connect and generate data
try:
    with connection.cursor() as cursor:
        admin_user_id = 1

        for brgy_key, count in barangay_counts.items():
            cursor.execute("SELECT id, geojson_data FROM barangay_boundaries WHERE barangay_name = %s LIMIT 1", (brgy_key,))
            result = cursor.fetchone()
            if not result:
                print(f"⚠️ No matching boundary found for '{brgy_key}'. Skipping.")
                continue

            barangay_id = result['id']
            geojson_str = result["geojson_data"]
            polygon = None
            if geojson_str:
                try:
                    geojson = json.loads(geojson_str)
                    if geojson and "geometry" in geojson and geojson["geometry"]:
                        polygon = shape(geojson["geometry"])
                except Exception as e:
                    print(f"❌ Error processing GeoJSON for '{brgy_key}': {e}.")

            print(f"Inserting {count} records for {brgy_key}...")
            generated_coords_count = 0

            for i in range(count):
                lat, lon = None, None
                if polygon:
                    minx, miny, maxx, maxy = polygon.bounds
                    # Increased retries from 10 to 100
                    for _ in range(100): 
                       point = Point(random.uniform(minx, maxx), random.uniform(miny, maxy))
                       if polygon.buffer(0).contains(point):
                           lat, lon = point.y, point.x
                           generated_coords_count += 1
                           break
                    
                    # --- NEW FALLBACK ---
                    # If it still failed after 100 tries, use the centroid
                    if lat is None:
                        centroid = polygon.centroid
                        lat, lon = centroid.y, centroid.x
                        generated_coords_count += 1
                    # --- END OF NEW FALLBACK ---

                first_name = fake.first_name()
                middle_name = fake.last_name() 
                last_name = fake.last_name()
                dob = get_weighted_dob() 
                gender = random.choices(genders, weights=gender_weights, k=1)[0]
                civil_status = random.choices(civil_statuses, weights=civil_status_weights, k=1)[0]
                pwd_id = fake.unique.bothify(text='PWD-??######')
                disability = random.choices(disability_types, weights=disability_weights, k=1)[0]
                employment_status = random.choices(employment_statuses, weights=employment_weights, k=1)[0]
                record_status_choice = random.choices(record_statuses, weights=record_status_weights, k=1)[0]

                address_line1 = fake.street_address()
                city = "Santo Tomas City" # Fixed to match your new default
                province = "Batangas"
                
                # --- *** START OF FIX 2: Add realistic dates based on status *** ---
                validation_date = None
                issue_date = None
                expiry_date = None
                created_at = None # <-- ADD THIS LINE
                db_status = record_status_choice # This will be 'draft', 'validated', or 'inactive'
                
                if record_status_choice == 'validated':
                    # Record is validated but not issued
                    validation_date = fake.date_time_between(start_date="-30d", end_date="-1d")
                    created_at = validation_date - datetime.timedelta(days=random.randint(1, 5)) # <-- ADD THIS LINE

                elif record_status_choice == 'issued':
                    # This is an ACTIVE issued ID
                    issue_date = fake.date_time_between(start_date="-3y", end_date="-1d") # Issued sometime in last 3 years
                    expiry_date = issue_date.date() + datetime.timedelta(days=random.randint(2*365, 3*365)) # Active for 2-3 more years
                    validation_date = issue_date - datetime.timedelta(days=random.randint(1, 5)) 
                    created_at = validation_date - datetime.timedelta(days=random.randint(1, 5)) # <-- ADD THIS LINE
                    db_status = 'issued' # Status in DB is 'issued'
                
                elif record_status_choice == 'expired':
                    # This is an EXPIRED issued ID
                    expiry_date = fake.date_time_between(start_date="-3y", end_date="-1d").date() # Expired sometime in last 3 years
                    issue_date = expiry_date - datetime.timedelta(days=3*365) # Issued 3 years before it expired
                    validation_date = issue_date - datetime.timedelta(days=random.randint(1, 5))
                    created_at = validation_date - datetime.timedelta(days=random.randint(1, 5)) # <-- ADD THIS LINE
                    db_status = 'issued' # CRITICAL: Status in DB is 'issued', report logic calculates 'expired'
                
                elif record_status_choice == 'inactive':
                    # Inactive records were likely 'issued' at some point
                    issue_date = fake.date_time_between(start_date="-4y", end_date="-1y")
                    expiry_date = issue_date.date() + datetime.timedelta(days=3*365) # Was valid
                    validation_date = issue_date - datetime.timedelta(days=random.randint(1, 5))
                    created_at = validation_date - datetime.timedelta(days=random.randint(1, 5)) # <-- ADD THIS LINE
                    db_status = 'inactive' # Status in DB is 'inactive'

                else: # This handles 'draft'
                    created_at = fake.date_time_between(start_date="-3y", end_date="now") # <-- ADD THIS BLOCK
                
                # 'draft' status has no dates, which is correct
                # --- *** END OF FIX 2 *** ---

                # --- *** START OF FIX 3: MODIFIED INSERT STATEMENT *** ---
                # Added validation_date, issue_date, expiry_date
                sql = """
                    INSERT INTO pwd_records (
                        pwd_id_number, first_name, middle_name, last_name, date_of_birth,
                        gender, civil_status, address_line1, barangay, city_municipality,
                        province, latitude, longitude, disability_type, employment_status,
                        created_by, status, barangay_id,
                        validation_date, issue_date, expiry_date, created_at 
                    )
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """ # ^-- Added created_at and one more %s
                values = (
                    pwd_id, first_name, middle_name, last_name, dob,
                    gender, civil_status, address_line1, brgy_key, city,
                    province, lat, lon, disability, employment_status,
                    admin_user_id, 
                    db_status, # Use the final status (draft, validated, issued, inactive)
                    barangay_id,
                    validation_date, # Add new date
                    issue_date,      # Add new date
                    expiry_date,     # Add new date
                    created_at       # <-- ADD THIS LINE
                )
                # --- *** END OF FIX 3 *** ---

                cursor.execute(sql, values)

            connection.commit()
            print(f"✅ Committed {count} records for {brgy_key}. ({generated_coords_count} with coordinates)")

        print("✅ All PWD records inserted successfully!")

except pymysql.MySQLError as e:
    print(f"❌ Database error: {e}")
    connection.rollback()
except ImportError as e:
     print(f"❌ Missing library. Please install it: pip install {e.name}")
except Exception as e:
    print(f"❌ An unexpected error occurred: {e}")
    connection.rollback()

finally:
    if connection.open:
        connection.close()
        print("Database connection closed.")