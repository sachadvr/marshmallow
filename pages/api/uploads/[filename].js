import fs from 'fs-extra';
import path from 'path';
import mime from 'mime-types';

export default async function handler(req, res) {
  const { filename } = req.query;

  if (!filename) {
    return res.status(400).send('No filename provided');
  }

  const filePath = path.join(process.cwd(), 'uploads', filename);

  if (!fs.existsSync(filePath)) {
    return res.status(404).send('File not found');
  }

  const fileContents = fs.readFileSync(filePath);
  const mimeType = mime.lookup(filePath);

  res.setHeader('Content-Type', mimeType);
  res.send(fileContents);
  
}
