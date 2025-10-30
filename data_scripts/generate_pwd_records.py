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

# Use Filipino localization for names - should prioritize Filipino names
fake = Faker("en_PH")

# Barangay names and counts
barangay_counts = {
    "Barangay 1": 42, "Barangay 2": 83, "Barangay 3": 47, "Barangay 4": 84,
    "San Agustin": 52, "San Antonio": 344, "San Bartolome": 159, "San Felix": 132,
    "San Fernando": 33, "San Francisco": 92, "San Isidro Norte": 61, "San Isidro Sur": 65,
    "San Joaquin": 72, "San Jose": 75, "San Juan": 75, "San Luis": 64, "San Miguel": 358,
    "San Pablo": 159, "San Pedro": 164, "San Rafael": 234, "San Roque": 272,
    "San Vicente": 481, "Santa Ana": 44, "Santa Anastacia": 214, "Santa Clara": 86,
    "Santa Cruz": 41, "Santa Elena": 38, "Santa Maria": 333, "Santiago": 135,
    "Santa Teresita": 71
}

# --- *** DIVERSIFICATION START *** ---

# Weighted choices for Gender
genders = ["Female", "Male", "Other"]
gender_weights = [0.50, 0.48, 0.02] # Example: Slightly more Female, few Other

# Weighted choices for Civil Status (Adjust weights as needed)
civil_statuses = ['Single', 'Married', 'Widowed', 'Separated']
civil_status_weights = [0.40, 0.45, 0.10, 0.05] # Example: More Single/Married

# Weighted choices for Disability Type (Adjust weights based on desired distribution)
disability_types = [
    "Physical Disability", "Visual Impairment", "Hearing Impairment",
    "Intellectual Disability", "Psychosocial Disability", "Multiple Disabilities"
]
disability_weights = [0.30, 0.20, 0.15, 0.15, 0.10, 0.10] # Example weights

# Weighted choices for Employment Status
employment_statuses = ['Unemployed', 'Employed', 'Self-employed', 'Student', 'Retired']
employment_weights = [0.40, 0.25, 0.15, 0.10, 0.10] # Example: More Unemployed

# Weighted choices for Record Status (ID Status)
record_statuses = ['validated', 'issued', 'pending_validation', 'inactive', 'expired']
record_status_weights = [0.50, 0.30, 0.10, 0.05, 0.05] # Example: Mostly validated/issued

# Weighted Age Ranges (min_age, max_age)
age_ranges = [(1, 17), (18, 30), (31, 45), (46, 60), (61, 90)]
age_weights = [0.15, 0.25, 0.30, 0.20, 0.10] # Example: More adults 18-60

def get_weighted_dob():
    chosen_range = random.choices(age_ranges, weights=age_weights, k=1)[0]
    min_age, max_age = chosen_range
    # Calculate birth year range based on current year
    today = date.today()
    latest_birth_year = today.year - min_age
    earliest_birth_year = today.year - max_age

    # Generate a random birth date within the chosen age range
    # Ensure earliest year is not before a reasonable limit, e.g., 1900
    earliest_birth_year = max(earliest_birth_year, today.year - 95) # Limit max age slightly beyond 90 if needed
    if earliest_birth_year > latest_birth_year: # Avoid invalid range if min/max age overlap weirdly near boundaries
        earliest_birth_year = latest_birth_year

    # Generate DOB. Ensure start_date is not before a reasonable minimum.
    try:
        start_date = date(earliest_birth_year, today.month, today.day)
    except ValueError: # Handle leap year day issues
         start_date = date(earliest_birth_year, today.month, today.day -1)

    try:
       end_date = date(latest_birth_year, today.month, today.day)
    except ValueError:
       end_date = date(latest_birth_year, today.month, today.day -1)

    # Ensure start_date is not after end_date
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
                    for _ in range(10): # Try 10 times
                       point = Point(random.uniform(minx, maxx), random.uniform(miny, maxy))
                       # Check if polygon contains the point; buffer(0) can fix self-intersection issues
                       if polygon.buffer(0).contains(point):
                           lat, lon = point.y, point.x
                           generated_coords_count += 1
                           break
                    # No warning spam if point not found
                    # if lat is None: print(f"⚠️ No point for {brgy_key} rec {i+1}")

                # --- *** Use weighted choices for generation *** ---
                first_name = fake.first_name()
                middle_name = fake.last_name() # Placeholder
                last_name = fake.last_name()
                dob = get_weighted_dob() # Use weighted age function
                gender = random.choices(genders, weights=gender_weights, k=1)[0]
                civil_status = random.choices(civil_statuses, weights=civil_status_weights, k=1)[0]
                pwd_id = fake.unique.bothify(text='PWD-??######')
                disability = random.choices(disability_types, weights=disability_weights, k=1)[0]
                employment_status = random.choices(employment_statuses, weights=employment_weights, k=1)[0]
                record_status = random.choices(record_statuses, weights=record_status_weights, k=1)[0]
                # --- *** End weighted choices *** ---

                address_line1 = fake.street_address()
                city = "Sto. Tomas"
                province = "Batangas"

                # --- *** MODIFIED INSERT STATEMENT *** ---
                # Added employment_status column
                sql = """
                    INSERT INTO pwd_records (
                        pwd_id_number, first_name, middle_name, last_name, date_of_birth,
                        gender, civil_status, address_line1, barangay, city_municipality,
                        province, latitude, longitude, disability_type, employment_status,
                        created_by, status, barangay_id
                    )
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """
                values = (
                    pwd_id, first_name, middle_name, last_name, dob,
                    gender, civil_status, address_line1, brgy_key, city,
                    province, lat, lon, disability, employment_status, # Added employment_status
                    admin_user_id, record_status, # Use weighted status
                    barangay_id
                )
                # --- *** END MODIFIED INSERT *** ---

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