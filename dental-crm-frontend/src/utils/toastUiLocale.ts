import DatePicker from 'tui-date-picker'
import TimePicker from 'tui-time-picker'

const UKRAINIAN_LOCALE = {
  titles: {
    DD: ['Неділя', 'Понеділок', 'Вівторок', 'Середа', 'Четвер', "П'ятниця", 'Субота'],
    D: ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
    MMM: ['Січ', 'Лют', 'Бер', 'Кві', 'Тра', 'Чер', 'Лип', 'Сер', 'Вер', 'Жов', 'Лис', 'Гру'],
    MMMM: [
      'Січень',
      'Лютий',
      'Березень',
      'Квітень',
      'Травень',
      'Червень',
      'Липень',
      'Серпень',
      'Вересень',
      'Жовтень',
      'Листопад',
      'Грудень'
    ]
  },
  titleFormat: 'MMMM yyyy',
  todayFormat: 'Сьогодні: DD, d MMMM yyyy',
  time: 'Час',
  date: 'Дата'
}

export const ensureUkLocale = () => {
  if (!DatePicker.localeTexts.uk) {
    DatePicker.localeTexts.uk = UKRAINIAN_LOCALE
  }

  if (!TimePicker.localeTexts.uk) {
    TimePicker.localeTexts.uk = {
      am: 'ДП',
      pm: 'ПП'
    }
  }
}
