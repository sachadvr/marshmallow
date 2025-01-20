import path from 'path';
import fs from 'fs';

export default function handler(req, res) {
  if (req.method !== 'GET') {
    return res.status(405).send('Method not allowed');
  }

  try {
    const dataPath = path.join(process.cwd(), 'data', 'content.txt');
    if (fs.existsSync(dataPath)) {
      const content = fs.readFileSync(dataPath, 'utf8');
      return res.status(200).send(content);
    } else {
      return res.status(404).send('No saved content found.');
    }
  } catch (err) {
    console.error(err);
    return res.status(500).send('Error reading content.');
  }
}
