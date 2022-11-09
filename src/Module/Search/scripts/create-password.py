import sys
import hashlib
import base64
import secrets
"""
tests encodage d'un pwd en clair avec un salt base64
"""
def decode():
    print( sys.argv )
    pwd = sys.argv[1]
    encodedSalt = sys.argv[2]
    salt = base64.b64decode(encodedSalt)
    print("pwd {} encoded salt {} {}".format(pwd, encodedSalt, salt))
    hash = hashlib.sha256()
    hash.update(salt)
    hash.update(pwd.encode())
    pass1 = hash.digest()
    hash = hashlib.sha256()
    hash.update(pass1)
    pwdEncoded = hash.digest()
    print(pwdEncoded)
    print(base64.b64encode(pwdEncoded).decode())


def encode():
    pwd = sys.argv[1]
    salt = secrets.token_bytes(32)
    saltEncoded = base64.b64encode(salt).decode()
    print("pwd {} encoded salt {} {}".format(pwd, saltEncoded, salt))
    hash = hashlib.sha256()
    hash.update(salt)
    hash.update(pwd.encode())
    pass1 = hash.digest()
    hash = hashlib.sha256()
    hash.update(pass1)
    pwdBytes = hash.digest()
    pwdEncoded = base64.b64encode(pwdBytes).decode()
    print(pwdEncoded)
    print("{} {}".format(pwdEncoded, saltEncoded))



print( __name__)

if __name__ == "__main__" :
    #decode()
    encode()
  
