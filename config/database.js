const mongoose = require('mongoose');

mongoose.connect(process.env.DATABASE_URL)
    .catch(error => {
        console.error(error);
        throw new Error(error);
    });

// shortcut to mongoose.connection object
const db = mongoose.connection;
	
db.on('connected', function() {
    console.log(`Connected to MongoDB at ${db.host}:${db.port}`);
});