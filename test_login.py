import urllib.request
import urllib.error
import json

url = "http://localhost/api/login"
data = json.dumps({"email": "admin@demo.com", "password": "password123"}).encode('utf-8')
req = urllib.request.Request(url, data=data, headers={'Content-Type': 'application/json', 'Accept': 'application/json'})

try:
    with urllib.request.urlopen(req) as response:
        print("Success:", response.status)
        print(response.read().decode('utf-8'))
except urllib.error.HTTPError as e:
    print("Error:", e.code)
    print(e.read().decode('utf-8'))
except Exception as e:
    print("Exception:", e)
