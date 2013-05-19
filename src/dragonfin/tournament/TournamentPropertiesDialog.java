package dragonfin.tournament;

import java.awt.*;
import java.awt.event.*;
import java.util.*;
import javax.swing.*;

public class TournamentPropertiesDialog extends JDialog
{
	Tournament tournament;
	static ResourceBundle strings = MainWindow.strings;

	JTextField eventNameEntry;
	JTextField locationEntry;
	JTextField beginDateEntry;
	JTextField beginTimeEntry;
	JTextField endDateEntry;
	JTextField endTimeEntry;
	JTextArea playerFieldsEntry;

	public TournamentPropertiesDialog(Window owner, Tournament tournament)
	{
		super(owner);
		this.tournament = tournament;
		setTitle("Tournament Properties");

		JPanel mainPane = makeMainPane();
		getContentPane().add(mainPane, BorderLayout.CENTER);

		JPanel buttonPane = new JPanel();
		getContentPane().add(buttonPane, BorderLayout.SOUTH);

		JButton okBtn = new JButton(strings.getString("ok_btn_caption"));
		okBtn.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onOkClicked();
			}});
		buttonPane.add(okBtn);

		JButton cancelBtn = new JButton(strings.getString("cancel_btn_caption"));
		cancelBtn.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				dispose();
			}});
		buttonPane.add(cancelBtn);

		pack();
		setDefaultCloseOperation(WindowConstants.DISPOSE_ON_CLOSE);
		setLocationRelativeTo(owner);
	}

	private void onOkClicked()
	{
		tournament.setEventName(eventNameEntry.getText());
		tournament.setEventLocation(locationEntry.getText());
		tournament.setEventBeginDate(beginDateEntry.getText());
		tournament.setEventBeginTime(beginTimeEntry.getText());
		tournament.setEventEndDate(endDateEntry.getText());
		tournament.setEventEndTime(endTimeEntry.getText());

		String [] parts = playerFieldsEntry.getText().split("\n");
		ArrayList<String> tmp = new ArrayList<String>();
		for (String s : parts) {
			s = s.trim();
System.out.println("-->"+s+"<--");
			if (s.length() != 0) {
				tmp.add(s);
			}
		}
		tournament.playerCustomFields = tmp.toArray(new String[0]);
		tournament.dirty = true;

		dispose();
	}

	private JPanel makeMainPane()
	{
		JPanel mainPane = new JPanel(new GridBagLayout());

		GridBagConstraints c0 = new GridBagConstraints();
		c0.gridx = 0;
		c0.gridy = 0;
		c0.gridwidth = 1;
		c0.gridheight = 1;

		GridBagConstraints c1 = new GridBagConstraints();
		c1.gridx = 1;
		c1.gridy = 0;
		c1.gridwidth = 1;
		c1.gridheight = 1;
		c1.fill = GridBagConstraints.HORIZONTAL;
		c1.weightx = 1.0;

		mainPane.add(new JLabel(strings.getString("properties.event_name")), c0);
		eventNameEntry = new JTextField(tournament.getEventName());
		mainPane.add(eventNameEntry, c1);

		c0.gridy = (++c1.gridy);

		mainPane.add(new JLabel(strings.getString("properties.event_location")), c0);
		locationEntry = new JTextField(tournament.getEventLocation());
		mainPane.add(locationEntry, c1);

		c0.gridy = (++c1.gridy);

		mainPane.add(new JLabel(strings.getString("properties.event_begin_date")), c0);
		beginDateEntry = new JTextField(tournament.getEventBeginDate());
		mainPane.add(beginDateEntry, c1);

		c0.gridy = (++c1.gridy);

		mainPane.add(new JLabel(strings.getString("properties.event_begin_time")), c0);
		beginTimeEntry = new JTextField(tournament.getEventBeginTime());
		mainPane.add(beginTimeEntry, c1);

		c0.gridy = (++c1.gridy);

		mainPane.add(new JLabel(strings.getString("properties.event_end_date")), c0);
		endDateEntry = new JTextField(tournament.getEventEndDate());
		mainPane.add(endDateEntry, c1);

		c0.gridy = (++c1.gridy);

		mainPane.add(new JLabel(strings.getString("properties.event_end_time")), c0);
		endTimeEntry = new JTextField(tournament.getEventEndTime());
		mainPane.add(endTimeEntry, c1);

		c0.gridy = (++c1.gridy);

		mainPane.add(new JLabel(strings.getString("properties.player_fields")), c0);
		StringBuilder sb = new StringBuilder();
		for (String s : tournament.playerCustomFields) {
			sb.append(s + "\n");
		}
		playerFieldsEntry = new JTextArea(sb.toString());
		mainPane.add(playerFieldsEntry, c1);

		return mainPane;
	}
}
