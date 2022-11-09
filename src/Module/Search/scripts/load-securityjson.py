import sys
import json
if __name__ == "__main__":
    with open(sys.argv[1]) as f:
        raw = f.read()
        print(raw)
        parsed = json.loads(raw)
        print(parsed)
        
    
