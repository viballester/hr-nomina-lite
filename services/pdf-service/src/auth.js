import { createHash, timingSafeEqual } from 'node:crypto';

const digest = (value) => createHash('sha256').update(value).digest();

// Solo Laravel conoce el token. Se comparan hashes de igual longitud en tiempo
// constante para no filtrar por tiempo de respuesta cuántos caracteres acierta.
export function requireToken(expected) {
  const expectedDigest = digest(expected);
  return (req, res, next) => {
    const header = req.get('authorization') ?? '';
    const token = header.startsWith('Bearer ') ? header.slice(7) : '';
    if (token && timingSafeEqual(digest(token), expectedDigest)) return next();
    res.status(401).json({ message: 'No autorizado' });
  };
}
