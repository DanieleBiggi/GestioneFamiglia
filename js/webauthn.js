function base64urlDecode(str) {
  str = str.replace(/-/g, '+').replace(/_/g, '/');
  const pad = str.length % 4;
  if (pad) {
    str += '='.repeat(4 - pad);
  }
  const binary = atob(str);
  return Uint8Array.from(binary, c => c.charCodeAt(0));
}

async function registerWebAuthn() {
  try {
    const resp = await fetch('webauthn_register.php');
    if (!resp.ok) {
      alert('Impossibile registrare una passkey. Effettua prima l\'accesso.');
      return;
    }
    const options = await resp.json();
    options.challenge = base64urlDecode(options.challenge);
    options.user.id = base64urlDecode(options.user.id);
    const cred = await navigator.credentials.create({ publicKey: options });
    const attestation = {
      id: cred.id,
      rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))),
      type: cred.type,
      response: {
        clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))),
        attestationObject: btoa(String.fromCharCode(...new Uint8Array(cred.response.attestationObject)))
      }
    };
    const save = await fetch('webauthn_register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(attestation)
    });
    if (save.ok) {
      alert('Passkey registrata con successo.');
    } else {
      alert('Salvataggio passkey non riuscito.');
    }
  } catch (err) {
    console.error('Errore nella registrazione WebAuthn', err);
    alert('Registrazione WebAuthn non riuscita.');
  }
}

async function loginWebAuthn() {
  try {
    if (!window.PublicKeyCredential || !navigator.credentials) {
      alert('Questo browser o dispositivo non supporta le passkey.');
      return;
    }

    const resp = await fetch('webauthn_login.php', {
      credentials: 'same-origin',
      cache: 'no-store'
    });

    if (!resp.ok) {
      let message = 'Impossibile avviare il login con impronta/passkey.';
      try {
        const error = await resp.json();
        if (error.error === 'not authenticated') {
          message = 'Questo dispositivo non è più riconosciuto. Accedi una volta con utente e password e riprova.';
        }
      } catch (_) {}
      alert(message);
      return;
    }

    const options = await resp.json();
    options.challenge = base64urlDecode(options.challenge);
    options.allowCredentials = (options.allowCredentials || []).map(c => ({
      ...c,
      id: base64urlDecode(c.id)
    }));

    if (!options.allowCredentials.length) {
      alert('Nessuna passkey registrata per questo dispositivo/account. Accedi normalmente e creane una da Sicurezza.');
      return;
    }

    const cred = await navigator.credentials.get({ publicKey: options });
    if (!cred) {
      alert('Nessuna passkey selezionata.');
      return;
    }

    const assertion = {
      id: cred.id,
      rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))),
      type: cred.type,
      response: {
        clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))),
        authenticatorData: btoa(String.fromCharCode(...new Uint8Array(cred.response.authenticatorData))),
        signature: btoa(String.fromCharCode(...new Uint8Array(cred.response.signature))),
        userHandle: cred.response.userHandle ? btoa(String.fromCharCode(...new Uint8Array(cred.response.userHandle))) : null
      }
    };

    const verify = await fetch('webauthn_login.php', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(assertion)
    });

    const result = await verify.json();
    if (verify.ok && result.success) {
      window.location.href = 'index.php';
      return;
    }

    alert(result.error || 'Autenticazione con passkey fallita.');
  } catch (err) {
    console.error('Errore nel login WebAuthn', err);

    if (err && err.name === 'NotAllowedError') {
      alert('Autenticazione annullata o non autorizzata dal dispositivo.');
    } else if (err && err.name === 'SecurityError') {
      alert('La passkey non può essere usata da questo indirizzo/sito. Verifica di essere sul dominio corretto e in HTTPS.');
    } else {
      alert('Non è stato possibile usare la passkey. Riprova oppure accedi con il passcode.');
    }
  }
}
