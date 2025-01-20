import path from 'path';
import fs from 'fs';

export default function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).send('Method not allowed');
  }

  try {
    const content = req.body.content || '';
    const dataDir = path.join(process.cwd(), 'data');

    if (!fs.existsSync(dataDir)) {
      fs.mkdirSync(dataDir);
    }

    fs.writeFileSync(path.join(dataDir, 'content.txt'), content, 'utf8');
    return res.status(200).send('Content saved successfully.');
  } catch (err) {
    console.error(err);
    return res.status(500).send('Error saving content.');
  }
}
