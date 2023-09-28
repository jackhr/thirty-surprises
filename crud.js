require('dotenv').config();
require('./config/database');
const User = require('./models/user');
const Playlist = require('./models/playlist');

let p, playlists;
let u, users;

Playlist.find({}, (err, plDocs) => playlists = plDocs);
User.find({}, (err, userDocs) => users = userDocs);