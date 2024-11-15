const bcrypt = require('bcrypt');
const mongoose = require('mongoose');
const Schema = mongoose.Schema;

const SALT_ROUNDS = 6;

const userSchema = new Schema({
    name: {
        type: String,
        required: true,
        trim: true
    },
    password: {
        type: String,
        trim: true,
        required: true
    },
}, {
    timestamps: true,
    toJSON: {
            transform: function(doc, ret) {
            delete ret.password;
            return ret;
        }
    }
});

userSchema.pre('save', function(next) {
    // Save the reference to the user doc
    const user = this;
    if (!user.isModified('password')) return next();
    // password has changed - salt and hash it
    bcrypt.hash(user.password, SALT_ROUNDS, function(err, hash) {
        if (err) return next(err);
        // Update the password property with the hash
        user.password = hash;
        return next();
    });
});

module.exports = mongoose.model('User', userSchema);