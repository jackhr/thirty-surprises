const mongoose = require('mongoose');
const Schema = mongoose.Schema;

const surpriseSchema = new Schema({
    title: String,
    description: String,
    number: Number,
    magnitude: {
        required: true,
        type: String,
        enum: ['small', 'medium', 'large'],
        default: "medium"
    },
    variety: {
        required: true,
        type: String,
        enum: ['cute', 'romantic', 'overdue', 'sweet', 'special', 'mystery'],
        default: "sweet"
    },
    iconClass: {
        required: true,
        type: String
    },
    viewed: Boolean,
    completedAt: Date,
    revealDate: Date,
},
{
    timestamps: true,
});

module.exports = mongoose.model('Surprise', surpriseSchema);